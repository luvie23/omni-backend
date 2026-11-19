
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OMNI RGB Portal Access</title>
</head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">

    <h2>Your OMNI RGB Contractor Portal Access</h2>

    <p>Hi {{ $contractorName }},</p>

    <p>
        Welcome to the OMNI RGB Contractor Portal!
        Your account is ready.
    </p>

    <p>Your login credentials are:</p>

    <table cellpadding="8" cellspacing="0"
           style="border-collapse:collapse;">
        <tr>
            <td><strong>Email</strong></td>
            <td>{{ $contractorEmail }}</td>
        </tr>
        <tr>
            <td><strong>Password</strong></td>
            <td>{{ $initialPassword }}</td>
        </tr>
        <tr>
            <td><strong>Certification Number</strong></td>
            <td>{{ $certificateNumber }}</td>
        </tr>
    </table>

    <p>
        Please change your password after your first login.
    </p>

    <p>
        If you need help accessing your account,
        please contact our team.
    </p>

    <p>
        Best regards,<br>
        <strong>The OMNI RGB Team</strong>
    </p>

</body>
</html>
