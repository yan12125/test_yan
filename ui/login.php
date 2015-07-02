<?php
require '../common_inc.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login</title>
<?php
External::setRelativePath('..');
echo External::loadJsCss();
?>
<script>
$(document).on('ready', function (e) {
    $('#reset').on('click', function (e) {
        $('#username').val('');
        $('#password').val('');
    });
    $('#submit').on('click', function (e) {
        callWrapper('login2', {
            username: $('#username').val(),
            password: $('#password').val(),
        }, function (response) {
            if(typeof response['error'] !== 'undefined')
            {
                alert(response['error']);
                return;
            }
            location.href = 'index.php?access_token=' + response['access_token'];
        });
    });
});
</script>
</head>
<body>
<center>
Username: <input type="text" id="username"><br>
Password: <input type="password" id="password"><br>
<button id="submit">Submit</button>
<button id="reset">Reset</button>
</center>
</body>
</html>
