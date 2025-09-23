<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us Form Submitted Successfully</title>
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
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-left: 4px solid #667eea;
            border-radius: 0 8px 8px 0;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .field-container:hover {
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        .field-label {
            font-weight: 700;
            color: #4a5568;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }
        .field-value {
            color: #2d3748;
            font-size: 16px;
            line-height: 1.5;
            margin: 0;
        }
        .message-field {
            background-color: #f0f4f8;
            border-left: 4px solid #38a169;
        }
        .message-field .field-value {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }
        .timestamp {
            background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%);
            border-left: 4px solid #38b2ac;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
            text-align: center;
        }
        .timestamp p {
            margin: 0;
            color: #234e52;
            font-size: 14px;
        }
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            .header, .content, .footer {
                padding: 20px;
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
            <!-- Name Field -->
            <div class="field-container">
                <span class="field-label">👤 Name</span>
                <p class="field-value">{{ $contactName }}</p>
            </div>

            <!-- Email Field -->
            <div class="field-container">
                <span class="field-label">📧 Email Address</span>
                <p class="field-value">{{ $contactEmail }}</p>
            </div>

            <!-- Subject Field -->
            <div class="field-container">
                <span class="field-label">📝 Subject</span>
                <p class="field-value">{{ $contactSubject }}</p>
            </div>

            <!-- Message Field -->
            <div class="field-container message-field">
                <span class="field-label">💬 Message</span>
                <p class="field-value">{{ $contactMessage }}</p>
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
                Please respond directly to the sender's email address.
            </p>
        </div>
    </div>
</body>
</html>