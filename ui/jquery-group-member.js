$.widget("custom.facebookGroupMemberCompleter", $.ui.autocomplete, {
    options: {
        source: function (request, response) {
            searchGroupAutoComplete(request.term, response, this.options.token, this.options.gid);
        }, 
        select: function (event, data) {
            var $parent = $(this).parent();
            var input = $parent.find('input');
            var displayField = $parent.find('.display-field');
            var btnDelete = $('<button>').text('Remove').button().click(function (event) {
                input.val('');
                displayField.hide();
                input.show();
            });
            displayField
                .empty()
                .append(
                    $('<a>')
                        .attr('href', 'https://www.facebook.com/' + data.item.uid)
                        .text(data.item.name)
                )
                .append(btnDelete)
                .show();
            input.hide();
        }
    }, 
    _create: function () {
        this._superApply(arguments);

        this._wrapper = $('<span>').addClass('facebookGroupMemberCompleter-wrapper');
        this._displayField = $('<span>');
        this._wrapper.insertAfter(this.element);
        this.element.detach().appendTo(this._wrapper);
        this._wrapper.append($("<span>").addClass('display-field'));
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
