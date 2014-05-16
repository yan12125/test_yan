function resizeAll()
{
    // $(document).height() and $(window).height() are different!
    // http://stackoverflow.com/questions/1304378
    // two steps below can't be exchanged! View in chrome debugger
    $('#texts').width($(window).width() - $('#controls').offset().left - 40);
    $('#titles').height($(window).height());
}

function updateTitles(cb)
{
    $('#titles').html('');
    callWrapper('list_titles', function(data){
        var titles = data.titles;
        for(var i = 0;i < titles.length;i++)
        {
            $('#titles').append('<div class="title">'+titles[i]+'</div>');
            if(data.locked[i])
            {
                $('.title:last').addClass("locked_title");
            }
        }
        $('.title').on('click', function(e){
            loadText($(this).text());
        });
        resizeAll();
        if(typeof cb == 'function')
        {
            cb(titles);
        }
    });
}

function selectTitleByUrl()
{
    // title may contain special chars such as ?
    // so encode in index.php and decode here
    var arr = {};
    var query = location.search;
    if(query[0] != '?')
    {
        // firefox memorizes previous value
        $('#texts').val('');
    }
    else
    {
        parse_str(query.substring(1), arr);
        loadText(arr.title);
    }
}

function loadPlugins()
{
    callWrapper('get_plugins', function(plugins){
        for(var i = 0;i < plugins.length;i++)
        {
            var p = plugins[i];
            $('#handler').append('<option value="'+p+'">'+p+'</option>');
        }
    });
}

function loadText(title, cb)
{
    $('#img_ok').css('display', 'none');
    $('#img_error').css('display', 'none');
    $('#test_result').text('');
    var titleArr = $('.title').map(function(i, e){ return $(e).text(); });
    if($.inArray(title, titleArr) == -1)
    {
        return false;
    }
    var titleButton = $('.title').hasText(title);
    $('#discard').button('option', 'disabled', false);
    if($('#token').val() !== '')
    {
        $('#save').button('option', 'disabled', false);
    }
    $('#texts').val('Loading...');
    $('.title').removeClass('selected');
    $(titleButton).addClass('selected');
    $('#caption').text(title);
    callWrapper('get_texts', { 'title': title }, function(response){
        // use val() instead of text()
        // http://stackoverflow.com/questions/8854288
        $('#texts').val(response.text).focus();
        $('#handler').val(response.handler);

        $('#titles').scrollTop(
            $('#titles').scrollTop() + 
            $('.title.selected').offset().top - 
            $('#titles').height()/2
        );
        if(response.locked)
        {
            $('#caption').append(' (<span style="color: orange">Locked</span>)');
        }
        if(typeof cb == 'function')
        {
            cb();
        }
    });
    return true;
}

function updateText(titleButton)
{
    callWrapper('update_text', {
        title: $(titleButton).text(), 
        texts: $('#texts').val(), 
        handler: $('#handler').val(), 
        access_token: $('#token').val()
    }, function(response){
        if(response.status == 'success')
        {
            loadText($(titleButton).text());
            $('#dialog').text('內容成功更新，共'+response.lines+'則');
        }
        else
        {
            $('#dialog').text(response.error);
        }
        $('#dialog').dialog({
            modal: true
        });
    });
}

function newTitle()
{
    var newTitle_impl = function(){
        var _title = $('#title_new').val();
        callWrapper('add_title', {
            title: _title, 
            access_token: $('#token').val()
        }, function(response){
            if(response.status == 'success')
            {
                updateTitles(function(){
                    loadText(_title);
                });
            }
            else
            {
                $('#dialog').text(response.error);
                $('#dialog').dialog({
                    modal: true
                });
            }
        });
    };
    $('#title_new').val('');
    $('#new_title_dialog').dialog({
        modal: true, 
        buttons: {
            'OK': function(){
                newTitle_impl();
                $(this).dialog('close');
            }
        }
    });
}

function testText()
{
    $('#img_ok').css('display', 'none');
    $('#img_error').css('display', 'none');
    $('#test_result').text('Running...');
    callWrapper('get_text_from_texts', {
        title: $('#caption').text(), 
        handler: $('#handler').val(), 
        texts: $('#texts').val()
    }, function(data){
        if(data.error)
        {
            $('#img_error').css('display', 'inline');
            $('#test_result').text(data.error);
        }
        else
        {
            $('#img_ok').css('display', 'inline');
            $('#test_result').html(escapeHtml(data.msg).replace("\n", "<br>"));
        }
    });
}

function searchText()
{
    var text_field = $('#texts')[0];

    var searchInText = function(isNewTitle) {
        // IE with versions <= 8 doesn't work
        if(typeof text_field.selectionStart == 'undefined')
        {
            return -1;
        }
        var search_term = $('#search_term').val();
        var current_content = $('#texts').val();
        var start_point = isNewTitle?0:text_field.selectionEnd;
        var idx = current_content.indexOf(search_term, start_point);
        if(idx != -1)
        {
            text_field.selectionStart = idx;
            text_field.selectionEnd = idx + search_term.length;
            text_field.focus();
            // chrome does not auto scroll to the selection
            return 0;
        }
        else
        {
            return -1;
        }
    }

    var search_term = $('#search_term').val();
    var search_results = JSON.parse($('#search_results').val());
    var current_content = $('#texts').text();

    // should always do the search again if #search_term has changed
    // and the latter causes search_results be empty
    if(search_results.length != 0)
    {
        if(searchInText(false) == 0)
        {
            return;
        }
    }
    var current_title = $('.title.selected:eq(0)').text();
    if(search_results.length == 0 || 
       search_results.back() == current_title)
    {
        callWrapper('search_text', {
            term: $('#search_term').val()
        }, function(data){
            if(data.length == 0)
            {
                $('#dialog').text('找不到指定的內容').dialog({ modal: true });
            }
            $('#search_results').val(JSON.stringify(data));
            loadText(data[0], function() {
                searchInText(true);
            });
        });
    }
    else
    {
        var current_title_index = search_results.indexOf(current_title);
        var next_title = search_results[current_title_index + 1];
        loadText(next_title, function() {
            searchInText(true);
        });
    }
}
