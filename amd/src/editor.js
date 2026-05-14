/**
 * AMD module: local_codejudge/editor
 * Initializes the Ace editor for code submission.
 */
define(['core/log'], function(Log) {

    return {
        init: function(elementId, language, boilerplates) {
            if (typeof ace === 'undefined') {
                Log.warn('Ace editor not loaded');
                return;
            }

            var editor = ace.edit(elementId);
            editor.setTheme('ace/theme/monokai');

            var modeMap = {
                python: 'ace/mode/python',
                c:      'ace/mode/c_cpp',
                cpp:    'ace/mode/c_cpp',
                java:   'ace/mode/java'
            };

            editor.session.setMode(modeMap[language] || 'ace/mode/text');
            editor.setValue(boilerplates[language] || '', -1);

            var select = document.getElementById('language-select');
            if (select) {
                select.addEventListener('change', function() {
                    var lang = this.value;
                    editor.session.setMode(modeMap[lang] || 'ace/mode/text');
                    editor.setValue(boilerplates[lang] || '', -1);
                });
            }

            // Expose globally for inline submit handler
            window.codeJudgeEditor = editor;
        }
    };
});
