// Like htmlspecialchars() in PHP
// reference: http://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
function escapeHtml(text)
{
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
        // making strings containing continuous spaces be displayed correctly
        .replace(/ /g, "&nbsp;");
}

function callWrapper(_action, _data, _success)
{
    if(typeof _data == 'undefined')
    {
        _data = {};
    }
    if(typeof _data == 'function')
    {
        _success = _data;
        _data = {};
    }
    _data.action = _action;
    var options = {
        url: Util.relativePath + '/wrapper.php', 
        type: 'POST', 
        dataType: 'json', 
        data: _data, 
    };
    if(typeof _success == 'function')
    {
        options.success = _success;
    }
    $.ajaxq('q_main', options);
}

var Util = {
    relative_path: '.'
};

function getAccessToken(cb)
{
    var parseLoginStatus = function(response, authUrl){
        if(response.status != 'connected')
        {
            top.location.href = authUrl;
            return;
        }
        var url = top.location.href;
        if(url.indexOf('?') !== -1)
        {
            top.location.href = url.substring(0, url.indexOf('?'));
        }
        if(response.authResponse.expiresIn >= 7201)
        {
            cb({
                'access_token': response.authResponse.accessToken, 
                'expires': response.authResponse.expiredIn
            });
            return;
        }
        callWrapper('exchange_token', {
            access_token: response.authResponse.accessToken
        }, function(response){
            if(typeof cb == 'function')
            {
                cb(response);
            }
        });
    };
    $.getScript('//connect.facebook.net/en_UK/all.js', function(){
        callWrapper('get_app_info', function(data){
            FB.init({ appId: data.appId });
            FB.getLoginStatus(function(response){
                parseLoginStatus(response, data.authUrl);
            });
        });
    });
}
