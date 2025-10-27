<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>User Account Deleted</title>
    </head>

    <body
        style="
            margin: 0;
            padding: 0;
            background-color: #eef1f6;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        "
    >
        <table
            role="presentation"
            width="100%"
            cellpadding="0"
            cellspacing="0"
            style="background-color: #eef1f6; padding: 40px 0"
        >
            <tr>
                <td align="center">
                    <table
                        role="presentation"
                        width="100%"
                        cellpadding="0"
                        cellspacing="0"
                        style="
                            max-width: 600px;
                            background-color: #ffffff;
                            border-radius: 10px;
                            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
                            overflow: hidden;
                        "
                    >
                        <tr>
                            <td
                                align="center"
                                style="
                                    background: linear-gradient(
                                        135deg,
                                        #4caf50,
                                        #2e7d32
                                    );
                                    padding: 40px 20px;
                                "
                            >
                                <h1
                                    style="
                                        margin: 0;
                                        font-size: 26px;
                                        color: #ffffff;
                                        letter-spacing: 0.5px;
                                    "
                                >
                                    {{ $subject }}
                                </h1>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style="padding: 30px">
                                <h2 style="font-size: 22px; margin-top: 0">
                                    Hello,
                                </h2>

                                <p style="font-size: 16px; line-height: 1.6">
                                    {{ $mail_message }}
                                </p>

                                <p style="margin-top: 30px; font-size: 16px">
                                    Thanks,<br />
                                    <strong
                                        >The {{ config('app.name') }}
                                        Team</strong
                                    >
                                </p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td
                                align="center"
                                style="
                                    background-color: #f9f9f9;
                                    padding: 20px;
                                    font-size: 13px;
                                    color: #888;
                                "
                            >
                                &copy; {{ now()->year }} {{ config('app.name')
                                }}. All rights reserved.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
