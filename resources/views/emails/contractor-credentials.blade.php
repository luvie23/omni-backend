
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

    <p>
        You can access the Contractor Portal using the link below:
    </p>

    <p style="margin:24px 0;">
        <a href="https://hub.omnirgb.com/"
           style="background-color:#222;color:#fff;padding:12px 24px;
                  text-decoration:none;border-radius:6px;
                  display:inline-block;font-weight:bold;">
            Login to Contractor Portal
        </a>
    </p>

    <p>
        Portal URL:
        <a href="https://hub.omnirgb.com/">
            https://hub.omnirgb.com/
        </a>
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
