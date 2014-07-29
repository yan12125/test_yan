$.widget("custom.facebookGroupMemberCompleter", $.ui.autocomplete, {
    options: {
        source: function (request, response) {
            searchGroupAutoComplete(request.term, response, this.options.token, this.options.gid);
        }, 
    }, 
    _create: function () {
        var that = this;

        this._superApply(arguments);

        this._btnDelete = $('<button>').text('Remove').button();
        this._btnDelete.click(function (event) {
            that.element.val('');
            that._displayField.hide();
            that.element.show();
        });

        this._link = $('<a>');

        this._displayField = $('<span>').addClass('display-field').hide();
        this._displayField.append(this._link).append(this._btnDelete);

        this._wrapper = $('<span>').addClass('facebookGroupMemberCompleter-wrapper');
        this._wrapper.insertAfter(this.element);
        this.element.detach().appendTo(this._wrapper);
        this._wrapper.append(this._displayField);

        this._setOption('select', function (event, data) {
            that.setToUid(data.item.uid, data.item.name);
        });
    }, 
    _renderItem: function (ul, item) {
        var li = $('<li>').addClass('facebook-group-member');
        var link = $('<a>');
        link.append($('<img>').attr('src', 'https://graph.facebook.com/' + item.uid + '/picture'));
        link.append(item.label);
        li.append(link);
        li.appendTo(ul);
        return li;
    }, 
    setToUid: function (uid, name) {
        if(uid.length == 0)
        {
            return;
        }
        var that = this;

        var setToUidInner = function (_name) {
            that._link.text(_name);
            that._displayField.show();
            that.element.hide();
        };

        this._link.attr('href', 'https://www.facebook.com/' + uid);
        if(name)
        {
            setToUidInner(name);
        }
        else
        {
            $.getJSON('https://graph.facebook.com/' + uid, function (response) {
                setToUidInner(response.name);
            });
        }
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
