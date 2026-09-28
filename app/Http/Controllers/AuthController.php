<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Contractor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Mail\ContractorWelcomeMail;
use App\Mail\ContractorImportSummaryMail;
use App\Models\CertifiedPerson;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // Optional: delete old tokens (forces single session)
        // $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ]
        ]);
    }

    public function registerContractor(Request $request)
    {
        $data = $request->validate([
            // user
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => 'required|string|min:8|confirmed',

            // contractor profile
            'company_name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'company_website_url' => 'nullable|url|max:255',
            'mailing_address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|max:10',
            'service_area' => 'required|string|max:255',

            // distributor
            'distributor_code' => 'required|string|size:2|alpha',
        ]);

        $user = DB::transaction(function () use ($data) {
            $contractor = Contractor::create([
                'company_name' => $data['company_name'],
                'email' => $data['email'],
                'contact_number' => $data['contact_number'],
                'company_website_url' => $data['company_website_url'] ?? null,
                'mailing_address' => $data['mailing_address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'zip' => $data['zip'],
                'service_area' => $data['service_area'],
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'contractor_id' => $contractor->id,
            ]);

            // Generate certification number
            $certNumber = $this->generateCertificateNumber(
                $data['distributor_code']
            );

            // Extra safety: retry on extremely unlikely collisions
            $attempts = 0;

            while (
                CertifiedPerson::where(
                    'certification_number',
                    $certNumber
                )->exists()
            ) {
                $attempts++;

                if ($attempts > 5) {
                    throw new \Exception(
                        'Could not generate a unique certification number.'
                    );
                }

                $certNumber = $this->generateCertificateNumber(
                    $data['distributor_code']
                );
            }

            // Create certified person linked to this user
            CertifiedPerson::create([
                'user_id' => $user->id,
                'contractor_id' => $contractor->id,
                'name' => $data['name'],
                'certification_number' => $certNumber,
            ]);

            // requires roles seeded already
            $user->assignRole('contractor');

            return $user;
        });

        // Sanctum token
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Contractor registered successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'contractor_profile' => $user->load('contractorProfile')->contractorProfile,
                'certified_person' => $user->load('certifiedPerson')->certifiedPerson,
            ],
            'token' => $token,
        ], 201);
    }


    public function importContractorsCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = fopen($request->file('file')->getRealPath(), 'r');

        if (!$file) {
            return response()->json([
                'message' => 'Unable to read uploaded file.',
            ], 422);
        }

        $header = fgetcsv($file);

        if (!$header) {
            fclose($file);

            return response()->json([
                'message' => 'CSV file is empty or missing a header row.',
            ], 422);
        }

        /**
         * |--------------------------------------------------------------------------
         * | Normalize headers
         * |--------------------------------------------------------------------------
         */

        $header = array_map(
            fn ($h) => strtolower(trim($h)),
            $header
        );

        $requiredHeaders = [
            'name',
            'email',
            'company_name',
            'company_website_url',
            'mailing_address',
            'city',
            'state',
            'zip',
            'service_area',
            'distributor',
        ];

        $missingHeaders = array_diff($requiredHeaders, $header);

        if (!empty($missingHeaders)) {
            fclose($file);

            return response()->json([
                'message' => 'CSV is missing required columns.',
                'missing_columns' => array_values($missingHeaders),
            ], 422);
        }

        $created = 0;
        $errors = [];
        $createdUsers = [];
        $resultRows = [];

        /**
         * |--------------------------------------------------------------------------
         * | Track emails already seen in this CSV
         * |--------------------------------------------------------------------------
         */

        $seenEmails = [];

        DB::beginTransaction();

        try {
            $rowIndex = 1;

            while (($row = fgetcsv($file)) !== false) {
                $rowIndex++;

                /**
                 * |--------------------------------------------------------------------------
                 * | Skip empty rows
                 * |--------------------------------------------------------------------------
                 */

                if (count(array_filter($row)) === 0) {
                    continue;
                }

                /**
                 * |--------------------------------------------------------------------------
                 * | Column mismatch
                 * |--------------------------------------------------------------------------
                 */

                if (count($row) !== count($header)) {
                    $reason = 'Column count does not match header count.';

                    $errors[] = [
                        'row' => $rowIndex,
                        'email' => null,
                        'errors' => [
                            'row' => [
                                $reason,
                            ],
                        ],
                    ];

                    $rowData = [];

                    foreach ($header as $index => $columnName) {
                        $rowData[$columnName] = $row[$index] ?? '';
                    }

                    $resultRows[] = [
                        'data' => $rowData,
                        'success' => false,
                        'result' => 'Failed: ' . $reason,
                    ];

                    continue;
                }

                $data = array_combine($header, $row);

                /**
                 * Clean common CSV whitespace
                 */
                if (isset($data['email'])) {
                    $data['email'] = trim($data['email']);
                }

                if (isset($data['state'])) {
                    $data['state'] = trim($data['state']);
                }

                /**
                 * |--------------------------------------------------------------------------
                 * | Check duplicate email within CSV
                 * |--------------------------------------------------------------------------
                 */

                $normalizedEmail = strtolower(
                    trim($data['email'] ?? '')
                );

                if (
                    $normalizedEmail !== '' &&
                    isset($seenEmails[$normalizedEmail])
                ) {
                    $reason =
                        "Duplicate email in CSV. First appeared on row {$seenEmails[$normalizedEmail]}.";

                    $errors[] = [
                        'row' => $rowIndex,
                        'email' => $data['email'] ?? null,
                        'errors' => [
                            'email' => [
                                $reason,
                            ],
                        ],
                    ];

                    $resultRows[] = [
                        'data' => $data,
                        'success' => false,
                        'result' => 'Failed: ' . $reason,
                    ];

                    continue;
                }

                if ($normalizedEmail !== '') {
                    $seenEmails[$normalizedEmail] = $rowIndex;
                }

                /**
                 * |--------------------------------------------------------------------------
                 * | Validate row
                 * |--------------------------------------------------------------------------
                 */

                $validator = Validator::make($data, [
                    'name' => 'required|string|max:255',

                    'email' => 'required|email|unique:users,email',

                    'company_name' => 'required|string|max:255',

                    'contact_number' => 'nullable|string|max:20',

                    'company_website_url' => 'nullable|string|max:255',

                    'mailing_address' => 'nullable|string|max:255',

                    'city' => 'nullable|string|max:100',

                    'state' => 'nullable|string|size:2',

                    'zip' => 'required|string|max:10',

                    'service_area' => 'required|string|max:255',

                    'distributor' => 'required|string|size:2|alpha',
                ]);

                if ($validator->fails()) {
                    $validationErrors =
                        $validator->errors()->toArray();

                    $errors[] = [
                        'row' => $rowIndex,
                        'email' => $data['email'] ?? null,
                        'errors' => $validationErrors,
                    ];

                    $messages = [];

                    foreach ($validationErrors as $fieldErrors) {
                        foreach ($fieldErrors as $message) {
                            $messages[] = $message;
                        }
                    }

                    $resultRows[] = [
                        'data' => $data,
                        'success' => false,
                        'result' =>
                            'Failed: ' . implode(' | ', $messages),
                    ];

                    continue;
                }

                $validated = $validator->validated();

                try {
                    /**
                     * |--------------------------------------------------------------------------
                     * | Generate password
                     * |--------------------------------------------------------------------------
                     */

                    $companyPart = ucfirst(
                        strtolower(
                            preg_replace(
                                '/[^a-zA-Z0-9]/',
                                '',
                                $validated['company_name']
                            )
                        )
                    );

                    $statePart = strtolower(
                        preg_replace(
                            '/[^a-zA-Z0-9]/',
                            '',
                            $validated['state']
                        )
                    );

                    $password =
                        $companyPart .
                        $statePart .
                        '!2026';

                    /**
                     * |--------------------------------------------------------------------------
                     * | Find existing contractor OR create one
                     * |--------------------------------------------------------------------------
                     */

                    $contractor = Contractor::firstOrCreate(
                        [
                            'company_name' => trim(
                                $validated['company_name']
                            ),
                        ],
                        [
                            'email' => $validated['email'],

                            'contact_number' =>
                                $validated['contact_number'] ?? null,

                            'company_website_url' =>
                                $validated['company_website_url'] ?? null,

                            'mailing_address' =>
                                $validated['mailing_address'] ?? null,

                            'city' =>
                                $validated['city'] ?? null,

                            'state' =>
                                strtoupper($validated['state']),

                            'zip' =>
                                $validated['zip'],

                            'service_area' =>
                                $validated['service_area'],
                        ]
                    );

                    /**
                     * |--------------------------------------------------------------------------
                     * | Create user
                     * |--------------------------------------------------------------------------
                     */

                    $user = User::create([
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'password' => Hash::make($password),
                        'contractor_id' => $contractor->id,
                    ]);

                    $user->assignRole('contractor');

                    /**
                     * |--------------------------------------------------------------------------
                     * | Generate certification number
                     * |--------------------------------------------------------------------------
                     */

                    $certNumber =
                        $this->generateCertificateNumber(
                            strtoupper(
                                trim($validated['distributor'])
                            )
                        );

                    $attempts = 0;

                    while (
                        CertifiedPerson::where(
                            'certification_number',
                            $certNumber
                        )->exists()
                    ) {
                        $attempts++;

                        if ($attempts > 5) {
                            throw new \Exception(
                                'Could not generate a unique certification number.'
                            );
                        }

                        $certNumber =
                            $this->generateCertificateNumber(
                                strtoupper(
                                    trim($validated['distributor'])
                                )
                            );
                    }

                    /**
                     * |--------------------------------------------------------------------------
                     * | Create certified person
                     * |--------------------------------------------------------------------------
                     */

                    $certifiedPerson =
                        $contractor
                            ->certifiedPeople()
                            ->create([
                                'user_id' => $user->id,
                                'name' => $validated['name'],
                                'certification_number' => $certNumber,
                            ]);

                    /**
                     * |--------------------------------------------------------------------------
                     * | Track created users
                     * |--------------------------------------------------------------------------
                     */

                    $created++;

                    $createdUsers[] = [
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'company_name' => $contractor->company_name,
                        'city' => $contractor->city,
                        'initial_password' => $password,
                        'certification_number' =>
                            $certifiedPerson->certification_number,
                    ];

                    /**
                     * |--------------------------------------------------------------------------
                     * | Track successful Excel row
                     * |--------------------------------------------------------------------------
                     */

                    $resultRows[] = [
                        'data' => $data,
                        'success' => true,
                        'result' => 'Success',
                    ];
                } catch (\Throwable $e) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'email' => $data['email'] ?? null,
                        'errors' => [
                            'exception' => [
                                $e->getMessage(),
                            ],
                        ],
                    ];

                    $resultRows[] = [
                        'data' => $data,
                        'success' => false,
                        'result' =>
                            'Failed: ' . $e->getMessage(),
                    ];
                }
            }

            fclose($file);

            DB::commit();

            /**
             * |--------------------------------------------------------------------------
             * | Generate Excel import report
             * |--------------------------------------------------------------------------
             */

            $spreadsheet = new Spreadsheet();

            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setTitle('Import Results');

            /**
             * Original CSV columns + result column
             */
            $outputHeaders = array_merge(
                $header,
                ['result']
            );

            /**
             * Header row
             */
            foreach (
                $outputHeaders as $columnIndex => $columnName
            ) {
                $sheet->setCellValue(
                    [$columnIndex + 1, 1],
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $columnName
                        )
                    )
                );
            }

            $highestColumn =
                $sheet->getHighestColumn();

            $sheet
                ->getStyle(
                    'A1:' . $highestColumn . '1'
                )
                ->getFont()
                ->setBold(true);

            $excelRow = 2;

            /**
             * |--------------------------------------------------------------------------
             * | Add rows to Excel
             * |--------------------------------------------------------------------------
             */

            foreach ($resultRows as $resultRow) {
                $columnIndex = 1;

                /**
                 * Original CSV data
                 */
                foreach ($header as $columnName) {
                    $sheet->setCellValue(
                        [$columnIndex, $excelRow],
                        $resultRow['data'][$columnName] ?? ''
                    );

                    $columnIndex++;
                }

                /**
                 * Result column
                 */
                $sheet->setCellValue(
                    [$columnIndex, $excelRow],
                    $resultRow['result']
                );

                /**
                 * Green = success
                 * Red = failure
                 */
                $fillColor =
                    $resultRow['success']
                        ? 'C6EFCE'
                        : 'FFC7CE';

                $sheet
                    ->getStyle(
                        'A' .
                        $excelRow .
                        ':' .
                        $highestColumn .
                        $excelRow
                    )
                    ->getFill()
                    ->setFillType(
                        Fill::FILL_SOLID
                    )
                    ->getStartColor()
                    ->setARGB($fillColor);

                $excelRow++;
            }

            /**
             * |--------------------------------------------------------------------------
             * | Excel formatting
             * |--------------------------------------------------------------------------
             */

            $sheet->freezePane('A2');

            if ($excelRow > 2) {
                $sheet->setAutoFilter(
                    'A1:' .
                    $highestColumn .
                    ($excelRow - 1)
                );
            }

            $highestColumnIndex =
                Coordinate::columnIndexFromString(
                    $highestColumn
                );

            for (
                $column = 1;
                $column <= $highestColumnIndex;
                $column++
            ) {
                $columnLetter =
                    Coordinate::stringFromColumnIndex(
                        $column
                    );

                $sheet
                    ->getColumnDimension($columnLetter)
                    ->setAutoSize(true);
            }

            /**
             * |--------------------------------------------------------------------------
             * | Save temporary Excel file
             * |--------------------------------------------------------------------------
             */

            $excelFileName =
                'contractor-import-results-' .
                now()->format('Y-m-d-His') .
                '.xlsx';

            $excelPath =
                storage_path(
                    'app/' . $excelFileName
                );

            $writer = new Xlsx(
                $spreadsheet
            );

            $writer->save(
                $excelPath
            );

            /**
             * |--------------------------------------------------------------------------
             * | Send one summary email with Excel attachment
             * |--------------------------------------------------------------------------
             */

            Mail::to(
                'luvie@lightsfordecorators.com'
            )->send(
                new ContractorImportSummaryMail(
                    $createdUsers,
                    $errors,
                    $excelPath
                )
            );

            /**
             * |--------------------------------------------------------------------------
             * | Delete temporary Excel file
             * |--------------------------------------------------------------------------
             */

            if (file_exists($excelPath)) {
                unlink($excelPath);
            }

            /**
             * |--------------------------------------------------------------------------
             * | Keep existing API response
             * |--------------------------------------------------------------------------
             */

            return response()->json([
                'message' => 'Import completed.',
                'created' => $created,
                'failed' => count($errors),
                'users' => $createdUsers,
                'errors' => $errors,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            if (is_resource($file)) {
                fclose($file);
            }

            return response()->json([
                'message' => 'Import failed completely.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateMyPassword(Request $request)
    {

        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);


        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.'
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        $user->tokens()
        ->where('id', '!=', $request->user()->currentAccessToken()->id)
        ->delete();

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }

    private function generateCertificateNumber(string $distributorCode): string
    {
        return 'OMNI' . strtoupper($distributorCode) . '-' . now()->format('ym') . '-' . $this->randomCode(8);
    }

    private function randomCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }


}
