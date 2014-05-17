$.widget("custom.facebookGroupMemberCompleter", $.ui.autocomplete, {
    options: {
        source: function (request, response) {
            searchGroupAutoComplete(request.term, response, this.options.token, this.options.gid);
        }
    }, 
    _renderItem: function (ul, item) {
        var li = $('<li>').addClass('facebook-group-member');
        var link = $('<a>');
        link.append($('<img>').attr('src', 'https://graph.facebook.com/' + item.uid + '/picture'));
        link.append(item.label);
        li.append(link);
        li.appendTo(ul);
        return li;
    }
});

function searchGroupAutoComplete(term, callback, token, gid)
{
    callWrapper("search_name_in_group", {
        gid: gid, 
        name: term, 
        access_token: token
    }, function (data) {
        for(var i = 0; i < data.length; i++)
        {
            data[i].label = data[i].name;
            data[i].value = data[i].uid;
        }
        callback(data);
    });
}
