(function ($) {
    var currentTree;

    function loadJStree(index) {
        
        //Initialize unique jsTree for each hierarchy
        var hierarchyTree = $("[name='hierarchy[" + index + "]']").siblings('#nav-tree');
        var initialTreeData;
        hierarchyTree.jstree({
            'core': {
                "check_callback" : function (operation, node, parent, position, more) {
                    if(operation === "copy_node" || operation === "move_node") {
                        if(more.is_multi) {
                            return false; // prevent moving node to different tree
                        }
                    }
                    return true; // allow everything else
                },
                'force_text': true,
                'data': hierarchyTree.data('jstree-data'),
            },
            'plugins': ['dnd', 'removenode', 'editlink', 'display']
        }).on('loaded.jstree', function() {
            // Open all nodes by default.
            hierarchyTree.jstree(true).open_all();
            initialTreeData = getTreeData(hierarchyTree.jstree(true));
        }).on('move_node.jstree', function(e, data) {
            // Open node after moving it.
            var parent = hierarchyTree.jstree(true).get_node(data.parent);
            hierarchyTree.jstree(true).open_all(parent);
        });

        $('#hierarchy-form').on('o:before-form-unload', function () {
            var treeInstance = hierarchyTree.jstree(true);
            // Pull in current data from hierarchy inputs before comparing data
            hierarchyTree.find('input[data-name], select[data-name]').each(function(index, element) {
                var nodeObj = treeInstance.get_node(element);
                var element = $(element);
                value = element.val();
                if (element.data('name') === 'itemSet') {
                    if (value !== '') {
                        value = parseInt(value, 10);
                    } else {
                        value = 0;
                    }
                }
                nodeObj.data[element.data('name')] = value;
            });
            if (initialTreeData !== getTreeData(treeInstance)) {
                Omeka.markDirty(this);
            }
        });
    }
    
    var getTreeData = function(tree) {
        var treeJson = tree.get_json();
        function deleteState (nodes) {
            nodes.forEach(function (node) {
                delete node.state;
                deleteState(node.children);
            });
        }
        deleteState(treeJson);
        return JSON.stringify(treeJson);
    }

    function replaceIndex(context, find, index) {
        context.find(':input').each(function() {
            var thisInput = $(this);
            if ($(this).attr('name') == undefined) {
                return;
            }
            var name = thisInput.attr('name').replace('[__' + find + '__]', '[' + index + ']');
            var label = thisInput.parents('.field').find('label').first();
            thisInput.attr('name', name);
            if (!thisInput.is(':hidden')) {
                thisInput.attr('id', name);
            }
            label.attr('for', name);
        });
    }

    $(document).ready(function () {
        var list = document.getElementById('hierarchies');
        var hierarchyIndex = 1;
        var jstreeIndex = 1;

        new Sortable(list, {
            draggable: ".block",
            handle: ".sortable-handle",
        });

        $('#new-hierarchy').on('click', '.hierarchy-add', function() {
            $.post(
                window.location.href,
                {layout: 'hierarchy'}
            ).done(function(data) {
                var newHierarchy = $(data).appendTo('#hierarchies');
                replaceIndex(newHierarchy, 'hierarchyIndex', hierarchyIndex);
                loadJStree(hierarchyIndex);
                hierarchyIndex++;
                Omeka.scrollTo(newHierarchy);
            });
        });

        $('#hierarchies .block').each(function () {
            $(this).data('hierarchyIndex', hierarchyIndex);
            replaceIndex($(this), 'hierarchyIndex', hierarchyIndex);
            loadJStree(hierarchyIndex);
            hierarchyIndex++;
        });

        $('#hierarchies').on('click', '.grouping-add', function (e) {
            currentTree = $(e.currentTarget).siblings('.jstree').jstree();
            nodeId = currentTree.create_node('#', {
                text: 'Grouping',
                data: {}
            });
            currentTree.toggleLinkEdit($('#' + nodeId));
        });

        $('#hierarchies').on('click', 'a.remove-value, a.restore-value', function (e) {
            e.preventDefault();
            var hierarchy = $(this).parents('.block');
            hierarchy.toggleClass('delete');
            hierarchy.find('a.remove-value, a.restore-value').removeClass('inactive');
            $(this).toggleClass('inactive');
            Omeka.markDirty($(this).closest('form'));
        });

        $('form').submit(function(e) {
            $('#hierarchies .block').each(function(hierarchyIndex) {
                var thisHierarchy = $(this);
                var thisJstree = thisHierarchy.find('#nav-tree').jstree();
                // Mark hidden input to true to delete hierarchy
                if (thisHierarchy.hasClass('delete')) {
                    thisHierarchy.find("input[name*='delete']").val(1);
                }
                // Prepare jstree data for submission
                thisHierarchy.find('input[data-name], select[data-name]').each(function(index, element) {
                    var nodeObj = thisJstree.get_node(element);
                    var element = $(element);
                    if (element.val()) {
                        value = element.val();
                        if (element.data('name') === 'itemSet' && value !== '') {
                            value = parseInt(value, 10);
                        }
                        nodeObj.data[element.data('name')] = value;
                    }
                    // Pass empty label and itemSet values to be handled by IndexController
                    if (element.data('name') == 'label' && !element.val()) {
                        nodeObj.data[element.data('name')] = '';
                    }
                    if (element.data('name') == 'itemSet' && !element.val()) {
                        nodeObj.data[element.data('name')] = '';
                    }
                });
                // Mark 'deleted' nodes and any descendents for deletion in DB
                thisHierarchy.find('.jstree-node').each(function(index, element) {
                    if (element.classList.contains('jstree-removenode-removed')) {
                        var nodeObj = thisJstree.get_node(element);
                        nodeObj.data['delete'] = true;
                        var descendants = thisJstree.get_json(element, { 'flat': true });
                        $.each(descendants, function (i, node) {
                            var nodeObj = thisJstree.get_node(node);
                            nodeObj.data['delete'] = true;
                        })
                    }
                });
                var jsTreeData = JSON.stringify(thisJstree.get_json());
                // Remove any newly added nodes marked for deletion
                thisHierarchy.find('.jstree-node').each(function(index, element) {
                    if (element.classList.contains('jstree-removenode-removed')) {
                        thisJstree.delete_node(element);
                    };
                });
                thisHierarchy.find("input[name*='data']").val(jsTreeData);
            });
        });

        $('.collapse-all').on('click', function() {
            $('.block-header.collapse .collapse').click();
        });

        $('.expand-all').on('click', function() {
            $('.block-header:not(.collapse) .expand').click();
        });

        // Toggle block visibility
        $('#hierarchies').on('click', '.expand,.collapse', function() {
            var blockToggle = $(this);
            blockToggle.parents('.block-header').toggleClass('collapse');
        });

        $('#add-hierarchy').on(
            'keyup',
            '.page-selector-filter',
            (function() {
            var timer = 0;
            return function() {
                clearTimeout(timer);
                timer = setTimeout(filterPages.bind(this), 400);
            }
        })());
    });
})(window.jQuery);
