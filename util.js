// Like htmlspecialchars() in PHP
// reference: http://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
function escapeHtml(text)
{
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
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
        url: 'wrapper.php', 
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
