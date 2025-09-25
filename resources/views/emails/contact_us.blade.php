<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Form Submission</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .email-container {
            max-width: 650px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #1771A3 0%, #0D1B2A 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .header .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
        }
        .field-container {
            background-color: #f8f9fa;
            border-left: 4px solid #1771A3;
            border-radius: 0 8px 8px 0;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .field-container:hover {
            box-shadow: 0 5px 15px rgba(23, 113, 163, 0.1);
        }
        .field-label {
            font-weight: 700;
            color: #0D1B2A;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }
        .field-value {
            color: #0D1B2A;
            font-size: 16px;
            line-height: 1.5;
            margin: 0;
        }
        .business-field {
            background-color: #fff8f0;
            border-left: 4px solid #F7941D;
        }
        .contact-field {
            background-color: #f0f6fc;
            border-left: 4px solid #1771A3;
        }
        .message-field {
            background-color: #f5f8fc;
            border-left: 4px solid #0D1B2A;
        }
        .message-field .field-value {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .row-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .row-container .field-container {
            flex: 1;
            margin-bottom: 0;
            width: calc(50% - 5px);
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 0;
            color: #0D1B2A;
            font-size: 14px;
        }
        .timestamp {
            background: linear-gradient(135deg, #f0f6fc 0%, #e6f3ff 100%);
            border-left: 4px solid #1771A3;
            padding: 15px;
            margin: 10px 0;
            border-radius: 0 8px 8px 0;
            text-align: center;
        }
        .timestamp p {
            margin: 0;
            color: #0D1B2A;
            font-size: 14px;
        }
        .reply-button {
            display: inline-block;
            background: linear-gradient(135deg, #1771A3 0%, #0D1B2A 100%);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            margin: 20px 0;
            transition: transform 0.3s ease;
        }
        .reply-button:hover {
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
            background: linear-gradient(135deg, #F7941D 0%, #1771A3 100%);
        }
        .null-value {
            color: #a0aec0;
            font-style: italic;
        }
        @media (max-width: 650px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            .header, .content, .footer {
                padding: 20px;
            }
            .row-container {
                flex-direction: column;
                gap: 0;
            }
            .row-container .field-container {
                margin-bottom: 20px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="icon">📧</div>
            <h1>New Contact Form Submission</h1>
            <p>You have received a new message from your website</p>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Name Field (Full Row) -->
            <div class="field-container">
                <span class="field-label">👤 Full Name</span>
                <p class="field-value">{{ $contactName }}</p>
            </div>

            <!-- Email and Phone Row -->
            <div class="row-container">
                <!-- Email Field -->
                <div class="field-container">
                    <span class="field-label">📧 Email Address</span>
                    <p class="field-value">{{ $contactEmail }}</p>
                </div>

                <!-- Phone Field -->
                <div class="field-container contact-field">
                    <span class="field-label">📞 Phone Number</span>
                    <p class="field-value">{{ $contactPhone ?: 'Not provided' }}</p>
                </div>
            </div>

            <!-- Business Information Row -->
            <div class="row-container">
                <!-- Business Name Field -->
                <div class="field-container business-field">
                    <span class="field-label">🏢 Business Name</span>
                    <p class="field-value">{{ $contactBusinessName ?: 'Not provided' }}</p>
                </div>

                <!-- Business Category Field -->
                <div class="field-container business-field">
                    <span class="field-label">🏷️ Business Category</span>
                    <p class="field-value">{{ $contactBusinessCategory ?: 'Not provided' }}</p>
                </div>
            </div>

            <!-- Subject Field (Full Row) -->
            <div class="field-container">
                <span class="field-label">📝 Subject</span>
                <p class="field-value">{{ $contactSubject }}</p>
            </div>

            <!-- Address Field -->
            <div class="field-container contact-field">
                <span class="field-label">📍 Address</span>
                <p class="field-value">{{ $contactAddress }}</p>
            </div>

            <!-- Message Field -->
            <div class="field-container message-field">
                <span class="field-label">💬 Message</span>
                <p class="field-value">{{ $contactMessage }}</p>
            </div>

            <!-- Reply Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="mailto:{{ $contactEmail }}?subject=Re: {{ $contactSubject }}" class="reply-button" style="color: white;">
                    📧 Reply to {{ $contactName }}
                </a>
            </div>

            <!-- Timestamp -->
            <div class="timestamp">
                <p><strong>⏰ Received:</strong> {{ $createdAt->format('F j, Y \a\t g:i A T') }}</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                This message was sent from your website's contact form.<br>
            </p>
            <p style="margin-top: 15px; font-size: 12px; color: #9ca3af;">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>