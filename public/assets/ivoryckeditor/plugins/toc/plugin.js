(function () {
    CKEDITOR.plugins.add('toc', {

        // Register the icons. They must match command names.
        icons: 'toc',
        lang: ['de', 'en'],
        // The plugin initialization logic goes inside this method.
        init: function (editor) {

            // Define the editor command that inserts a timestamp.
            editor.addCommand('insertToc', {

                allowedContent: '*[id,name,class]{margin-left}',
                // Define the function that will be fired when the command is executed.
                exec: function (editor) {
                    //remove already exisiting tocs...
                    var tocElements = editor.document.$.getElementsByName("tableOfContents");
                    for (var j = tocElements.length; j > 0; j--) {
                        var oldid = tocElements[j - 1].getAttribute("id").toString();
                        editor.document.getById(oldid).remove();
                    }
                    //find all headings
                    var list = [],
                        nodes = editor.editable().find('h1,h2,h3,h4,h5,h6,');

                    if (nodes.count() == 0) {
                        alert(editor.lang.toc.notitles);
                        return;
                    }
                    //iterate over headings
                    var tocItems = "";
                    for (var i = 0; i < nodes.count(); i++) {
                        var node = nodes.getItem(i),
                            //level can be used for indenting. it contains a number between 0 (h1) and 5 (h6).
                            level = parseInt(node.getName().substr(1)) - 1;

                        var text = new CKEDITOR.dom.text(CKEDITOR.tools.trim(node.getText()), editor.document);
                        var id = "";
                        //check if heading has id
                        if (node.hasAttribute("id")) {
                            id = node.getText();
                            node.setAttribute('id', node.getText().replace(/"|“|”/g, ""));
                        } else {
                            id = text.getText().replace(/[^A-Za-z0-9\_\-]/g, "+");
                            node.setAttribute('id', node.getText().replace(/"|“|”/g, ""));
                        }
                        //create name-attribute based on id
                        node.setAttribute('name', node.getText());

                        //build toc entries as divs
                        tocItems = tocItems + '<div style="margin-left:' + level * 30 + 'px" id="' + id.toString().replace(/"|“|”/g, "") + '-toc" name="tableOfContents">' + '<a href="#' + text.getText().toString().replace(/"|“|”/g, "") + '"><i>' + text.getText().toString() + '</i></a></div>';
                    }

                    //output toc
                    var tocNode = '<div class="ka-table-of-contents collapsed"><p name="tableOfContents" id="main-toc"><b><u>' + editor.lang.toc.ToC + '</u></b></p>' + tocItems + '</div>';
                    editor.insertHtml(tocNode);

                    // Remove element with attribute name
                    editor.document.$.querySelectorAll('[name]').forEach(function (el) {
                        el.removeAttribute("data-cke-saved-name");
                        el.removeAttribute("name");
                    })
                }
            });

            // Create the toolbar button that executes the above command.
            editor.ui.addButton('toc', {
                label: editor.lang.toc.tooltip,
                command: 'insertToc',
                icon: this.path + 'icons/toc.png',
                toolbar: 'links'
            });
        }
    }
    )
})
    ();
