// Like htmlspecialchars() in PHP
// reference: http://stackoverflow.com/questions/1787322
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
    var arr = queryString.parse(location.search);
    if(arr.access_token)
    {
        cb({ access_token: arr.access_token });
        return;
    }
    callWrapper('get_token', function(response){
        cb(response);
    });
}

// create the same result as timestr() in lib/util.php
function timestr(timestamp)
{
    var s = null;
    if(typeof timestamp == 'undefined')
    {
        s = new Date();
    }
    else
    {
        // Date() accepts timestamps in milliseconds
        s = new Date(timestamp * 1000);
    }
    return sprintf("%4d/%02d/%02d %02d:%02d:%02d", s.getFullYear(), s.getMonth(), s.getDate(), s.getHours(), s.getMinutes(), s.getSeconds());
}

$.fn.extend({
    setBusy: function (isBusy) {
        if(isBusy)
        {
            this.html(escapeHtml('           ')).addClass('busy_img');
        }
        else
        {
            this.text('').removeClass('busy_img');
        }
        return this;
    }, 
    hasText: function(text){
        return this.filter(function(){
            return $(this).text() == text;
        });
    }
});

// back(): an C++ STL style
Array.prototype.back = function() {
    if(this.length >= 1)
    {
        return this[this.length - 1];
    }
    else
    {
        return /* undefined */ ;
    }
};

String.prototype.countRange = function(c, start, end) {
    return (this.substring(start, end).split(c).length - 1);
};
