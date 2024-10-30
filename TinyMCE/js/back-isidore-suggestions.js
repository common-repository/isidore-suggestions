(function() {
    tinymce.PluginManager.add('isidoreSuggestions', function( editor, url ) {
        //register a custom isidore format
        editor.on('init', function(e) {
            editor.formatter.register(
                'isidorespan', {
                    inline: 'span',
                    classes: 'isidore',
                }
            );
        })
        //register the button
        editor.addButton( 'addIsidoreSpan', {
            title: 'Span isidore',
            image : url + '/isidore-suggestions.png',
            onclick: function() {
                editor.execCommand('mceToggleFormat', false, 'isidorespan');
            },
            onpostrender: function() {
                var btn = this;
                editor.on('init', function() {
                    editor.formatter.formatChanged('isidorespan', function(state) {
                        btn.active(state);
                    });
                });
            }
        });  
    });
})();