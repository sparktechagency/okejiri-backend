<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boosting Rejection</title>
</head>

<body
    style="margin:0; padding:0; background-color:#f4f6f8; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="background-color:#f4f6f8; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:600px; background-color:#ffffff; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.08); overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center"
                            style="background:linear-gradient(135deg,#f44336,#c62828); padding: 40px 20px;">
                            <h1 style="margin:0; font-size:26px; color:#ffffff; font-weight:600; letter-spacing:0.5px;">
                                {{ $subject }}
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">
                            <h2 style="font-size:20px; margin-top:0; font-weight:600;">Hello {{ $name }},</h2>

                            @if ($type == 'rejected')
                                <p style="font-size:16px; line-height:1.6; margin-bottom:16px;">
                                    Thank you for submitting your boosting request on {{ config('app.name') }}.
                                </p>
                            @elseif ($type == 'removed')
                                <p style="font-size:16px; line-height:1.6; margin-bottom:16px;">
                                    Your previously approved boosting on {{ config('app.name') }} has been removed.
                                </p>
                            @endif
                            @if ($type == 'rejected')
                                <p style="font-size:16px; line-height:1.6; color:#d32f2f; margin-bottom:16px;">
                                    After reviewing your request, unfortunately we’re unable to approve your boosting at
                                    this time.
                                </p>
                            @elseif ($type == 'removed')
                                <p style="font-size:16px; line-height:1.6; color:#d32f2f; margin-bottom:16px;">
                                    If you have any questions, please contact our support team.
                                </p>
                            @endif


                            @if (!empty($reason))
                                <p
                                    style="font-size:16px; line-height:1.6; background-color:#fdecea; color:#b71c1c; padding:12px; border-radius:6px; margin-bottom:20px;">
                                    <strong>Reason for rejection:</strong> {{ $reason }}
                                </p>
                            @endif

                            <p style="font-size:16px; line-height:1.6; margin-bottom:24px;">
                                This may be due to policy violations, incomplete information, or other restrictions. You
                                can update your details and re-submit, or contact our support team for further
                                assistance.
                            </p>

                            <p style="margin-top:30px; font-size:16px;">
                                Regards,<br>
                                <strong>The {{ config('app.name') }} Team</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#f9f9f9; padding:20px; font-size:13px; color:#888;">
                            &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>

</html>
