<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CalculatorController extends Controller
{
    private const PRODUCTS = [
        '88012-1' => [
            'description' => 'RGBW (3000K) 12 Volt LED Single Light',
            'wholesale' => 3.99,
            'gold' => 3.81,
        ],
        '88012-5' => [
            'description' => 'RGBW (3000K) 12 Volt LED Set of 5',
            'wholesale' => 15.95,
            'gold' => 15.25,
        ],

        '88210' => [
            'description' => 'Aluminum Track Single',
            'wholesale' => 7.32,
            'gold' => 7.10,
        ],
        '88220' => [
            'description' => 'Aluminum Double Track',
            'wholesale' => 7.96,
            'gold' => 7.74,
        ],
        '88230-90' => [
            'description' => 'Aluminum Parapet Track - 90 Degree Output',
            'wholesale' => 7.96,
            'gold' => 7.74,
        ],
        '88230-A' => [
            'description' => 'Aluminum Parapet Track - Angled Output',
            'wholesale' => 7.96,
            'gold' => 7.74,
        ],
        '88240-90' => [
            'description' => '2-Piece Parapet Track - 90 Degree Output',
            'wholesale' => 10.35,
            'gold' => 10.05,
        ],
        '88240-A' => [
            'description' => '2-Piece Parapet Track - Angled Output',
            'wholesale' => 9.55,
            'gold' => 9.27,
        ],
        '88222' => [
            'description' => 'Solid Track',
            'wholesale' => 7.96,
            'gold' => 7.74,
        ],

        '89110-350' => [
            'description' => 'Enhanced - 350 Watt Power Supply (12V) in waterproof box',
            'wholesale' => 259.15,
            'gold' => 254.07,
        ],
        '89110-600' => [
            'description' => 'Enhanced - 600 Watt Power Supply (12V) in waterproof box',
            'wholesale' => 352.44,
            'gold' => 345.55,
        ],

        '88120' => [
            'description' => 'Single Channel WiFi/Bluetooth Controller',
            'wholesale' => 70.86,
            'gold' => 63.05,
        ],
        '88121' => [
            'description' => 'Enhanced - Signal Booster',
            'wholesale' => 10.76,
            'gold' => 12.39,
        ],
        '88122' => [
            'description' => 'Enhanced - Splitter 1 to 2',
            'wholesale' => 5.38,
            'gold' => 6.22,
        ],
        '88123' => [
            'description' => 'Enhanced - Power T Injector',
            'wholesale' => 4.84,
            'gold' => 5.59,
        ],
        '88126' => [
            'description' => 'Female Adapter',
            'wholesale' => 2.42,
            'gold' => 2.80,
        ],

        '88312-1' => [
            'description' => "1' Extension",
            'wholesale' => 1.18,
            'gold' => 1.55,
        ],
        '88312-3' => [
            'description' => "3' Extension",
            'wholesale' => 1.83,
            'gold' => 2.12,
        ],
        '88312-5' => [
            'description' => "5' Extension",
            'wholesale' => 2.37,
            'gold' => 2.73,
        ],
        '88312-10' => [
            'description' => "10' Extension",
            'wholesale' => 3.39,
            'gold' => 3.91,
        ],
        '88312-25' => [
            'description' => "25' Extension",
            'wholesale' => 6.86,
            'gold' => 7.92,
        ],
        '88312-50' => [
            'description' => "50' Extension",
            'wholesale' => 13.45,
            'gold' => 15.53,
        ],

        '1800162-500' => [
            'description' => 'Power Injection Wire - 16/2 (feet)',
            'wholesale' => 0.30,
            'gold' => 0.30,
        ],
    ];

    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            /*
            |--------------------------------------------------------------------------
            | Estimator CalculatorMaterial Li - user inputs
            |--------------------------------------------------------------------------
            */

            'runs' => ['required', 'array', 'min:1', 'max:15'],
            'runs.*' => ['nullable', 'numeric', 'min:0'],

            'corners' => ['nullable', 'numeric', 'min:0'],

            'track_style' => ['nullable', 'string'],
            'track_color_type' => ['nullable', 'string'],
            'custom_track_color' => ['nullable', 'string'],
            'custom_track_price' => ['nullable', 'numeric', 'min:0'],

            'splitters' => ['nullable', 'numeric', 'min:0'],

            'extensions' => ['nullable', 'array'],
            'extensions.1' => ['nullable', 'numeric', 'min:0'],
            'extensions.3' => ['nullable', 'numeric', 'min:0'],
            'extensions.5' => ['nullable', 'numeric', 'min:0'],
            'extensions.10' => ['nullable', 'numeric', 'min:0'],
            'extensions.25' => ['nullable', 'numeric', 'min:0'],
            'extensions.50' => ['nullable', 'numeric', 'min:0'],

            'solid_track_feet' => ['nullable', 'numeric', 'min:0'],
            'solid_track_color' => ['nullable', 'string'],

            'distance_to_first_light' => ['nullable', 'numeric', 'min:0'],
            'gaps_over_50' => ['nullable', 'numeric', 'min:0'],
            'additional_controllers' => ['nullable', 'numeric', 'min:0'],

            'adjusted_350w' => ['nullable', 'numeric', 'min:0'],
            'adjusted_600w' => ['nullable', 'numeric', 'min:0'],

            'waste_percentage' => ['nullable', 'numeric', 'min:0'],

            /*
             * Workbook F1 pricing selector.
             */
            'price_level' => ['nullable', 'in:wholesale,gold'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid calculator data.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        /*
        |--------------------------------------------------------------------------
        | Inputs
        |--------------------------------------------------------------------------
        */

        $runs = array_map(
            fn ($run) => (float) ($run ?? 0),
            $data['runs']
        );

        $totalFeet = array_sum($runs);

        $corners =
            (float) ($data['corners'] ?? 0);

        $trackStyle =
            $data['track_style'] ?? 'Single Track';

        $trackColorType =
            $data['track_color_type'] ?? '8019 - Dark Brown';

        $customTrackColor =
            $data['custom_track_color'] ?? '';

        $customTrackPrice =
            (float) ($data['custom_track_price'] ?? 0);

        $splitters =
            (float) ($data['splitters'] ?? 0);

        /*
         * Workbook H34 default.
         */
        $solidTrackFeet =
            (float) ($data['solid_track_feet'] ?? 10);

        $solidTrackColor =
            $data['solid_track_color'] ?? '';

        $distanceToFirstLight =
            (float) ($data['distance_to_first_light'] ?? 0);

        $gapsOver50 =
            (float) ($data['gaps_over_50'] ?? 0);

        $additionalControllers =
            (float) ($data['additional_controllers'] ?? 0);

        /*
         * Workbook C42 default = 5%.
         */
        $wastePercentage =
            (float) ($data['waste_percentage'] ?? 0.05);

        /*
         * Workbook F1 defaults to ws.
         */
        $priceLevel =
            $data['price_level'] ?? 'wholesale';

        /*
         * Workbook H39 / H40.
         *
         * These are the PSU quantities actually used
         * in the Order List.
         */
        $adjusted350 =
            (float) ($data['adjusted_350w'] ?? 0);

        $adjusted600 =
            (float) ($data['adjusted_600w'] ?? 0);

        $manualExtensions = [
            1 => (float) ($data['extensions'][1] ?? 0),
            3 => (float) ($data['extensions'][3] ?? 0),
            5 => (float) ($data['extensions'][5] ?? 0),
            10 => (float) ($data['extensions'][10] ?? 0),
            25 => (float) ($data['extensions'][25] ?? 0),
            50 => (float) ($data['extensions'][50] ?? 0),
        ];

        /*
        |--------------------------------------------------------------------------
        | Light calculations
        |--------------------------------------------------------------------------
        */

        $lightCalculation =
            $this->calculateLights($runs);

        $singleLights =
            $lightCalculation['single_lights']
            + ($corners * 3);

        $fiveLightSets =
            $lightCalculation['five_piece_units']
            - ($singleLights / 5);

        /*
         * Workbook total-light value.
         */
        $totalLights =
            $lightCalculation['five_piece_units'] * 5;

        /*
        |--------------------------------------------------------------------------
        | Extension calculations
        |--------------------------------------------------------------------------
        */

        $automaticExtensions =
            $this->calculateAutomaticExtensions(
                $distanceToFirstLight
            );

        $totalExtensions = [];

        foreach ([1, 3, 5, 10, 25, 50] as $feet) {
            $totalExtensions[$feet] =
                $manualExtensions[$feet]
                + $automaticExtensions[$feet];
        }

        /*
        |--------------------------------------------------------------------------
        | Power calculations
        |--------------------------------------------------------------------------
        */

        $wattsNeeded =
            (($totalFeet * 12) / 8) * 0.96;

        /*
         * These are recommendations only.
         *
         * They do not automatically get added to the
         * material cost.
         */
        $estimatedPowerSupplies =
            $this->calculateEstimatedPowerSupplies(
                $wattsNeeded
            );

        $estimatedAvailableWatts =
            ($estimatedPowerSupplies['350w'] * 300)
            + ($estimatedPowerSupplies['600w'] * 510);

        /*
         * Adjusted/selected PSU values.
         */
        $adjustedAvailableWatts =
            ($adjusted350 * 300)
            + ($adjusted600 * 510);

        /*
        |--------------------------------------------------------------------------
        | System components
        |--------------------------------------------------------------------------
        */

        $controllers =
            $this->excelRoundUp(
                $totalLights / 2040
            )
            + $additionalControllers;

        $signalBoosters =
            ($distanceToFirstLight > 10 ? 1 : 0)
            + $gapsOver50;

        $powerInjectors =
            $totalFeet > 50
                ? $this->excelRoundUp(
                    $totalFeet / 50
                ) + 1
                : 1;

        $femaleAdapters =
            $controllers;

        $powerInjectionWire =
            $powerInjectors > 0
                ? ($powerInjectors - 1) * 70
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Order List
        |--------------------------------------------------------------------------
        */

        $orderList = [];

        /*
         * Standard track.
         */
        $standardTrackQuantity =
            ($totalFeet * 12) / 40;

        $trackMap = [
            'Single Track' =>
                '88210',

            '2 Piece (Double) Track' =>
                '88220',

            'Parapet Track- 90' =>
                '88230-90',

            'Parapet Track - Angle' =>
                '88230-A',

            '2 - Piece Parapet - 90' =>
                '88240-90',

            '2-Piece Parapet - Angle  Track' =>
                '88240-A',
        ];

        $isCustomTrack =
            strtolower(trim($trackColorType)) === 'custom';

        foreach ($trackMap as $style => $sku) {
            $quantity = 0;

            if (
                ! $isCustomTrack
                && $trackStyle === $style
            ) {
                $quantity =
                    $standardTrackQuantity;
            }

            $this->addProductRow(
                orderList: $orderList,
                sku: $sku,
                quantity: $quantity,
                wastePercentage: $wastePercentage,
                priceLevel: $priceLevel,
                applyWaste: true
            );
        }

        /*
         * Custom track.
         */
        $customTrackQuantity =
            $isCustomTrack
                ? ($totalFeet * 12) / 40
                : 0;

        $customTrackWasteQuantity =
            $this->excelRoundUp(
                $customTrackQuantity
                * (1 + $wastePercentage)
            );

        $customTrackTotal =
            $customTrackPrice
            * $customTrackQuantity;

        $customTrackWasteCost =
            $customTrackWasteQuantity > 0
                ? (
                    $customTrackWasteQuantity
                    - $customTrackQuantity
                ) * $customTrackPrice
                : 0;

        $orderList[] = [
            'sku' =>
                'CUSTOM-TRACK',

            'description' =>
                'Custom Track Color: '
                . $customTrackColor
                . ' '
                . $trackStyle,

            'quantity' =>
                $customTrackQuantity,

            'waste_adjusted_quantity' =>
                $customTrackWasteQuantity,

            'unit_price' =>
                $customTrackPrice,

            'total_cost' =>
                $customTrackTotal,

            'waste_additional_cost' =>
                $customTrackWasteCost,
        ];

        /*
         * Solid track.
         *
         * ROUNDUP(H34 * 12, 0) / 40
         */
        $solidTrackQuantity =
            $this->excelRoundUp(
                $solidTrackFeet * 12
            ) / 40;

        $this->addProductRow(
            orderList: $orderList,
            sku: '88222',
            quantity: $solidTrackQuantity,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel,
            applyWaste: true
        );

        /*
         * Single lights.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '88012-1',
            quantity: $singleLights,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel,
            applyWaste: true
        );

        /*
         * Five-light sets.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '88012-5',
            quantity: $fiveLightSets,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel,
            applyWaste: true
        );

        /*
         * Adjusted power supplies.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '89110-350',
            quantity: $adjusted350,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        $this->addProductRow(
            orderList: $orderList,
            sku: '89110-600',
            quantity: $adjusted600,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        /*
         * Controller.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '88120',
            quantity: $controllers,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        /*
         * Signal booster.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '88121',
            quantity: $signalBoosters,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        /*
         * Splitters.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '88122',
            quantity: $splitters,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        /*
         * Power injectors.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '88123',
            quantity: $powerInjectors,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        /*
         * Female adapters.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '88126',
            quantity: $femaleAdapters,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        /*
         * Extensions.
         */
        $extensionSkuMap = [
            1 => '88312-1',
            3 => '88312-3',
            5 => '88312-5',
            10 => '88312-10',
            25 => '88312-25',
            50 => '88312-50',
        ];

        foreach ($extensionSkuMap as $feet => $sku) {
            $this->addProductRow(
                orderList: $orderList,
                sku: $sku,
                quantity: $totalExtensions[$feet],
                wastePercentage: $wastePercentage,
                priceLevel: $priceLevel
            );
        }

        /*
         * Power injection wire.
         */
        $this->addProductRow(
            orderList: $orderList,
            sku: '1800162-500',
            quantity: $powerInjectionWire,
            wastePercentage: $wastePercentage,
            priceLevel: $priceLevel
        );

        /*
        |--------------------------------------------------------------------------
        | Material totals
        |--------------------------------------------------------------------------
        */

        $materialCost = 0;
        $wasteAdditionalCost = 0;

        foreach ($orderList as $row) {
            $materialCost +=
                $row['total_cost'];

            $wasteAdditionalCost +=
                $row['waste_additional_cost'];
        }

        $materialCostWithWaste =
            $materialCost
            + $wasteAdditionalCost;

        $costPerFoot =
            $totalFeet > 0
                ? $materialCost / $totalFeet
                : 0;

        $costPerFootWithWaste =
            $totalFeet > 0
                ? $materialCostWithWaste / $totalFeet
                : 0;

        /*
        |--------------------------------------------------------------------------
        | Data needed by PriceLaborMarginEstimator
        |--------------------------------------------------------------------------
        |
        | Vue can now perform all of the what-if labor,
        | pricing and margin calculations without calling
        | Laravel again.
        |
        */

        $laborEstimatorExtensionFeet =
            $distanceToFirstLight
            + ($manualExtensions[1] * 1)
            + ($manualExtensions[3] * 3)
            + ($manualExtensions[5] * 5)
            + ($manualExtensions[10] * 10)
            + ($manualExtensions[25] * 25)
            + ($manualExtensions[50] * 50);

        $powerSupplyCount =
            $adjusted350
            + $adjusted600;

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'input' => [
                'runs' =>
                    $runs,

                'total_feet' =>
                    $totalFeet,

                'corners' =>
                    $corners,

                'track_style' =>
                    $trackStyle,

                'track_color_type' =>
                    $trackColorType,

                'custom_track_color' =>
                    $customTrackColor,

                'custom_track_price' =>
                    $customTrackPrice,

                'splitters' =>
                    $splitters,

                'solid_track_feet' =>
                    $solidTrackFeet,

                'solid_track_color' =>
                    $solidTrackColor,

                'distance_to_first_light' =>
                    $distanceToFirstLight,

                'gaps_over_50' =>
                    $gapsOver50,

                'additional_controllers' =>
                    $additionalControllers,

                'adjusted_350w' =>
                    $adjusted350,

                'adjusted_600w' =>
                    $adjusted600,

                'waste_percentage' =>
                    $wastePercentage,

                'price_level' =>
                    $priceLevel,
            ],

            'lights' => [
                'sections' =>
                    $lightCalculation['sections'],

                'single_lights_before_corners' =>
                    $lightCalculation['single_lights'],

                'single_lights' =>
                    $singleLights,

                'five_piece_units' =>
                    $lightCalculation['five_piece_units'],

                'five_light_sets_ordered' =>
                    $fiveLightSets,

                'total_lights' =>
                    $totalLights,
            ],

            'power' => [
                'watts_needed' =>
                    $this->roundValue($wattsNeeded),

                /*
                 * Excel F39/F40 recommendation.
                 */
                'estimated' => [
                    '350w_quantity' =>
                        $estimatedPowerSupplies['350w'],

                    '600w_quantity' =>
                        $estimatedPowerSupplies['600w'],

                    'available_watts' =>
                        $estimatedAvailableWatts,
                ],

                /*
                 * Excel H39/H40 user-adjusted quantities.
                 */
                'adjusted' => [
                    '350w_quantity' =>
                        $adjusted350,

                    '600w_quantity' =>
                        $adjusted600,

                    'available_watts' =>
                        $adjustedAvailableWatts,

                    'enough_power' =>
                        $adjustedAvailableWatts >= $wattsNeeded,
                ],
            ],

            'system' => [
                'controllers' =>
                    $controllers,

                'signal_boosters' =>
                    $signalBoosters,

                'splitters' =>
                    $splitters,

                'power_injectors' =>
                    $powerInjectors,

                'female_adapters' =>
                    $femaleAdapters,

                'power_injection_wire_feet' =>
                    $powerInjectionWire,
            ],

            'extensions' => [
                'automatic' =>
                    $automaticExtensions,

                'manual' =>
                    $manualExtensions,

                'total' =>
                    $totalExtensions,
            ],

            'order_list' =>
                $orderList,

            'pricing' => [
                'material_cost' =>
                    $this->money(
                        $materialCost
                    ),

                'waste_additional_cost' =>
                    $this->money(
                        $wasteAdditionalCost
                    ),

                'material_cost_with_waste' =>
                    $this->money(
                        $materialCostWithWaste
                    ),

                'cost_per_foot' =>
                    $this->money(
                        $costPerFoot
                    ),

                'cost_per_foot_with_waste' =>
                    $this->money(
                        $costPerFootWithWaste
                    ),
            ],

            /*
             * This contains the values Vue needs to
             * reproduce PriceLaborMarginEstimator.
             */
            'labor_estimator' => [
                'calculated' => [
                    'linear_feet' =>
                        $totalFeet,

                    'corners' =>
                        $corners,

                    'power_supply_count' =>
                        $powerSupplyCount,

                    'extension_feet' =>
                        $laborEstimatorExtensionFeet,

                    'material_cost_with_waste' =>
                        $this->money(
                            $materialCostWithWaste
                        ),
                ],

                /*
                 * Default yellow-cell values from
                 * PriceLaborMarginEstimator.
                 *
                 * Vue can initialize its fields using
                 * these and then let the user change them.
                 */
                'defaults' => [
                    'install_feet_per_hour' =>
                        30,

                    'corner_minutes' =>
                        30,

                    'power_box_minutes' =>
                        40,

                    'extension_minutes_per_foot' =>
                        2,

                    'crew_cost_per_hour' =>
                        25,

                    'hardware_percentage' =>
                        0.05,

                    'charge_per_foot_option_1' =>
                        19.20,

                    'charge_per_foot_option_2' =>
                        25.00,

                    'charge_per_foot_option_3' =>
                        30.00,

                    'lift_cost' =>
                        0,

                    'other_cost' =>
                        0,
                ],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Light calculations
    |--------------------------------------------------------------------------
    */

    private function calculateLights(array $runs): array
    {
        $sections = [
            20 => 0,
            15 => 0,
            10 => 0,
            5 => 0,
            1 => 0,
        ];

        foreach ($runs as $feet) {
            $inches =
                $feet * 12;

            if ($inches <= 0) {
                continue;
            }

            /*
             * 20-light section = 160"
             */
            $twenty =
                floor($inches / 160);

            /*
             * 15-light section = 120"
             */
            $fifteen =
                floor(
                    (
                        $inches
                        - ($twenty * 160)
                    ) / 120
                );

            /*
             * 10-light section = 80"
             */
            $ten =
                floor(
                    (
                        $inches
                        - ($twenty * 160)
                        - ($fifteen * 120)
                    ) / 80
                );

            /*
             * 5-light section = 40"
             */
            $five =
                floor(
                    (
                        $inches
                        - ($twenty * 160)
                        - ($fifteen * 120)
                        - ($ten * 80)
                    ) / 40
                );

            /*
             * Single light = 8"
             */
            $single =
                $this->excelRoundUp(
                    (
                        $inches
                        - ($twenty * 160)
                        - ($fifteen * 120)
                        - ($ten * 80)
                        - ($five * 40)
                    ) / 8
                );

            $sections[20] +=
                $twenty;

            $sections[15] +=
                $fifteen;

            $sections[10] +=
                $ten;

            $sections[5] +=
                $five;

            $sections[1] +=
                $single;
        }

        /*
         * Convert sections into five-light units.
         */
        $fivePieceUnits =
            ($sections[20] * 4)
            + ($sections[15] * 3)
            + ($sections[10] * 2)
            + $sections[5];

        return [
            'sections' =>
                $sections,

            'single_lights' =>
                $sections[1],

            'five_piece_units' =>
                $fivePieceUnits,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Automatic extension calculation
    |--------------------------------------------------------------------------
    */

    private function calculateAutomaticExtensions(
        float $distanceFeet
    ): array {
        $inches =
            $distanceFeet * 12;

        $extension50 =
            floor($inches / 600);

        $extension25 =
            floor(
                (
                    $inches
                    - ($extension50 * 600)
                ) / 300
            );

        $extension10 =
            floor(
                (
                    $inches
                    - ($extension50 * 600)
                    - ($extension25 * 300)
                ) / 120
            );

        $extension5 =
            floor(
                (
                    $inches
                    - ($extension50 * 600)
                    - ($extension25 * 300)
                    - ($extension10 * 120)
                ) / 60
            );

        $remaining =
            $inches
            - ($extension50 * 600)
            - ($extension25 * 300)
            - ($extension10 * 120)
            - ($extension5 * 60);

        $extension3 =
            floor($remaining / 36);

        $leftoverFeet =
            $this->excelRoundUp(
                fmod($remaining, 36) / 12
            );

        if ($leftoverFeet >= 3) {
            $extension3 += 1;
            $extension1 = 0;
        } else {
            $extension1 =
                $leftoverFeet;
        }

        return [
            1 => $extension1,
            3 => $extension3,
            5 => $extension5,
            10 => $extension10,
            25 => $extension25,
            50 => $extension50,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Estimated power supply lookup
    |--------------------------------------------------------------------------
    */

    private function calculateEstimatedPowerSupplies(
        float $watts
    ): array {
        $table = [
            0 => [
                '350w' => 1,
                '600w' => 0,
            ],

            300 => [
                '350w' => 0,
                '600w' => 1,
            ],

            510 => [
                '350w' => 1,
                '600w' => 1,
            ],

            810 => [
                '350w' => 0,
                '600w' => 2,
            ],

            1020 => [
                '350w' => 1,
                '600w' => 2,
            ],

            1320 => [
                '350w' => 2,
                '600w' => 2,
            ],

            1620 => [
                '350w' => 1,
                '600w' => 3,
            ],

            1830 => [
                '350w' => 0,
                '600w' => 4,
            ],

            2040 => [
                '350w' => 1,
                '600w' => 4,
            ],

            2340 => [
                '350w' => 0,
                '600w' => 5,
            ],

            2550 => [
                '350w' => 1,
                '600w' => 5,
            ],

            2850 => [
                '350w' => 0,
                '600w' => 6,
            ],
        ];

        $selected =
            $table[0];

        foreach ($table as $minimum => $row) {
            if ($watts >= $minimum) {
                $selected =
                    $row;
            }
        }

        return $selected;
    }

    /*
    |--------------------------------------------------------------------------
    | Order List product helper
    |--------------------------------------------------------------------------
    */

    private function addProductRow(
        array &$orderList,
        string $sku,
        float $quantity,
        float $wastePercentage,
        string $priceLevel,
        bool $applyWaste = false
    ): void {
        if (! isset(self::PRODUCTS[$sku])) {
            return;
        }

        $product =
            self::PRODUCTS[$sku];

        $unitPrice =
            (float) $product[$priceLevel];

        $wasteAdjustedQuantity = 0;
        $wasteAdditionalCost = 0;

        if ($applyWaste) {
            $wasteAdjustedQuantity =
                $this->excelRoundUp(
                    $quantity
                    * (1 + $wastePercentage)
                );

            if ($wasteAdjustedQuantity > 0) {
                $wasteAdditionalCost =
                    (
                        $wasteAdjustedQuantity
                        - $quantity
                    ) * $unitPrice;
            }
        }

        $orderList[] = [
            'sku' =>
                $sku,

            'description' =>
                $product['description'],

            'quantity' =>
                $quantity,

            'waste_adjusted_quantity' =>
                $wasteAdjustedQuantity,

            'unit_price' =>
                $unitPrice,

            'total_cost' =>
                $unitPrice * $quantity,

            'waste_additional_cost' =>
                $wasteAdditionalCost,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function excelRoundUp(float $value): float
    {
        return ceil($value);
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }

    private function roundValue(float $value): float
    {
        return round($value, 4);
    }
}
