<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OMNI Contractor Import Summary</title>
</head>

<body style="font-family: Arial, sans-serif; color: #1e293b; line-height: 1.5;">

    <h1>OMNI Contractor Import Summary</h1>

    <p>
        The contractor CSV import has completed.
    </p>

    <table
        cellpadding="10"
        cellspacing="0"
        border="1"
        style="border-collapse: collapse; margin-bottom: 30px;"
    >
        <tr>
            <td><strong>Successful</strong></td>
            <td>{{ count($createdUsers) }}</td>
        </tr>

        <tr>
            <td><strong>Failed</strong></td>
            <td>{{ count($errors) }}</td>
        </tr>

        <tr>
            <td><strong>Total Processed</strong></td>
            <td>{{ count($createdUsers) + count($errors) }}</td>
        </tr>
    </table>


    {{-- Successful Accounts --}}

    <h2>Successfully Created Accounts</h2>

    @if(count($createdUsers) > 0)

        <table
            cellpadding="8"
            cellspacing="0"
            border="1"
            width="100%"
            style="border-collapse: collapse; margin-bottom: 30px;"
        >
            <thead>
                <tr style="background: #f1f5f9;">
                    <th align="left">Name</th>
                    <th align="left">Company</th>
                    <th align="left">Email</th>
                    <th align="left">Password</th>
                    <th align="left">Certification #</th>
                </tr>
            </thead>

            <tbody>
                @foreach($createdUsers as $user)
                    <tr>
                        <td>
                            {{ $user['name'] }}
                        </td>

                        <td>
                            {{ $user['company_name'] }}
                        </td>

                        <td>
                            {{ $user['email'] }}
                        </td>

                        <td>
                            <strong>
                                {{ $user['initial_password'] }}
                            </strong>
                        </td>

                        <td>
                            {{ $user['certification_number'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    @else

        <p>No accounts were successfully created.</p>

    @endif


    {{-- Failed Accounts --}}

    <h2>Failed Accounts</h2>

    @if(count($errors) > 0)

        <table
            cellpadding="8"
            cellspacing="0"
            border="1"
            width="100%"
            style="border-collapse: collapse;"
        >
            <thead>
                <tr style="background: #f1f5f9;">
                    <th align="left">CSV Row</th>
                    <th align="left">Email</th>
                    <th align="left">Reason</th>
                </tr>
            </thead>

            <tbody>

                @foreach($errors as $error)

                    <tr>
                        <td>
                            {{ $error['row'] ?? 'N/A' }}
                        </td>

                        <td>
                            {{ $error['email'] ?? 'N/A' }}
                        </td>

                        <td>
                            @foreach(($error['errors'] ?? []) as $field => $messages)

                                @foreach((array) $messages as $message)

                                    <div>
                                        <strong>{{ ucfirst($field) }}:</strong>
                                        {{ $message }}
                                    </div>

                                @endforeach

                            @endforeach
                        </td>
                    </tr>

                @endforeach

            </tbody>
        </table>

    @else

        <p>No failures. All accounts were successfully created.</p>

    @endif

</body>
</html>
