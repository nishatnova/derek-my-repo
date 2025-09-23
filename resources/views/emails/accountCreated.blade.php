<html>
<head>
    <title>Account Created Successfully</title>
</head>

<style>
    .mail-header {
        color: black;
    }

</style>
<body class="mail-header">
<h3>Hello {{ $user->name ?? $user->email }},</h3>
<p>Thank you for registering with us. Your account has been created successfully!</p>
<p>We are happy to have you on board.</p>
<p>Thank You!</p>
</body>
</html>