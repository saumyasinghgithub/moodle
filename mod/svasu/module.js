// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript helper function for SVASU module.
 *
 * @package   mod-svasu
 * @copyright 2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

mod_svasu_launch_next_sco = null;
mod_svasu_launch_prev_sco = null;
mod_svasu_activate_item = null;
mod_svasu_parse_toc_tree = null;
svasu_layout_widget = null;

window.svasu_current_node = null;

function underscore(str) {
    str = String(str).replace(/.N/g,".");
    return str.replace(/\./g,"__");
}

M.mod_svasu = {};

M.mod_svasu.init = function(Y, nav_display, navposition_left, navposition_top, hide_toc, collapsetocwinsize, toc_title, window_name, launch_sco, scoes_nav) {
    var svasu_disable_toc = false;
    var svasu_hide_nav = true;
    var svasu_hide_toc = true;
    if (hide_toc == 0) {
        if (nav_display !== 0) {
            svasu_hide_nav = false;
        }
        svasu_hide_toc = false;
    } else if (hide_toc == 3) {
        svasu_disable_toc = true;
    }

    scoes_nav = Y.JSON.parse(scoes_nav);
    var svasu_buttons = [];
    var svasu_bloody_labelclick = false;
    var svasu_nav_panel;

    Y.use('button', 'dd-plugin', 'panel', 'resize', 'gallery-sm-treeview', function(Y) {

        Y.TreeView.prototype.getNodeByAttribute = function(attribute, value) {
            var node = null,
                domnode = Y.one('a[' + attribute + '="' + value + '"]');
            if (domnode !== null) {
                node = svasu_tree_node.getNodeById(domnode.ancestor('li').get('id'));
            }
            return node;
        };

        Y.TreeView.prototype.openAll = function () {
            this.get('container').all('.yui3-treeview-can-have-children').each(function(target) {
                this.getNodeById(target.get('id')).open();
            }, this);
        };

        Y.TreeView.prototype.closeAll = function () {
            this.get('container').all('.yui3-treeview-can-have-children').each(function(target) {
                this.getNodeById(target.get('id')).close();
            }, this);
        }

        var svasu_parse_toc_tree = function(srcNode) {
            var SELECTORS = {
                    child: '> li',
                    label: '> li, > a',
                    textlabel : '> li, > span',
                    subtree: '> ul, > li'
                },
                children = [];

            srcNode.all(SELECTORS.child).each(function(childNode) {
                var child = {},
                    labelNode = childNode.one(SELECTORS.label),
                    textNode = childNode.one(SELECTORS.textlabel),
                    subTreeNode = childNode.one(SELECTORS.subtree);

                if (labelNode) {
                    var title = labelNode.getAttribute('title');
                    var scoid = labelNode.getData('scoid');
                    child.label = labelNode.get('outerHTML');
                    // Will be good to change to url instead of title.
                    if (title && title !== '#') {
                        child.title = title;
                    }
                    if (typeof scoid !== 'undefined') {
                        child.scoid = scoid;
                    }
                } else if (textNode) {
                    // The selector did not find a label node with anchor.
                    child.label = textNode.get('outerHTML');
                }

                if (subTreeNode) {
                    child.children = svasu_parse_toc_tree(subTreeNode);
                }

                children.push(child);
            });

            return children;
        };

        mod_svasu_parse_toc_tree = svasu_parse_toc_tree;

        var svasu_activate_item = function(node) {
            if (!node) {
                return;
            }
            // Check if the item is already active, avoid recursive calls.
            var content = Y.one('#svasu_content');
            var old = Y.one('#svasu_object');
            if (old) {
                var svasu_active_url = Y.one('#svasu_object').getAttribute('src');
                var node_full_url = M.cfg.wwwroot + '/mod/svasu/loadSCO.php?' + node.title;
                if (node_full_url === svasu_active_url) {
                    return;
                }
                // Start to unload iframe here
                if(!window_name){
                    content.removeChild(old);
                    old = null;
                }
            }
            // End of - Avoid recursive calls.

            svasu_current_node = node;
            if (!svasu_current_node.state.selected) {
                svasu_current_node.select();
            }

            svasu_tree_node.closeAll();
            var url_prefix = M.cfg.wwwroot + '/mod/svasu/loadSCO.php?';
            var el_old_api = document.getElementById('svasuapi123');
            if (el_old_api) {
                el_old_api.parentNode.removeChild(el_old_api);
            }

            var obj = document.createElement('iframe');
            obj.setAttribute('id', 'svasu_object');
            obj.setAttribute('type', 'text/html');
            obj.setAttribute('allowfullscreen', 'allowfullscreen');
            obj.setAttribute('webkitallowfullscreen', 'webkitallowfullscreen');
            obj.setAttribute('mozallowfullscreen', 'mozallowfullscreen');
            if (!window_name && node.title != null) {
                obj.setAttribute('src', url_prefix + node.title);
            }
            if (window_name) {
                var mine = window.open('','','width=1,height=1,left=0,top=0,scrollbars=no');
                if(! mine) {
                    alert(M.util.get_string('popupsblocked', 'svasu'));
                }
                mine.close();
            }

            if (old) {
                if(window_name) {
                    var cwidth = svasuplayerdata.cwidth;
                    var cheight = svasuplayerdata.cheight;
                    var poptions = svasuplayerdata.popupoptions;
                    poptions = poptions + ',resizable=yes'; // Added for IE (MDL-32506).
                    svasu_openpopup(M.cfg.wwwroot + "/mod/svasu/loadSCO.php?" + node.title, window_name, poptions, cwidth, cheight);
                }
            } else {
                content.prepend(obj);
            }

            if (svasu_hide_nav == false) {
                if (nav_display === 1 && navposition_left > 0 && navposition_top > 0) {
                    Y.one('#svasu_object').addClass(cssclasses.svasu_nav_under_content);
                }
                svasu_fixnav();
            }
            svasu_tree_node.openAll();
        };

        mod_svasu_activate_item = svasu_activate_item;

        /**
         * Enables/disables navigation buttons as needed.
         * @return void
         */
        var svasu_fixnav = function() {
            var skipprevnode = svasu_skipprev(svasu_current_node);
            var prevnode = svasu_prev(svasu_current_node);
            var skipnextnode = svasu_skipnext(svasu_current_node);
            var nextnode = svasu_next(svasu_current_node);
            var upnode = svasu_up(svasu_current_node);

            svasu_buttons[0].set('disabled', ((skipprevnode === null) ||
                        (typeof(skipprevnode.scoid) === 'undefined') ||
                        (scoes_nav[skipprevnode.scoid].isvisible === "false") ||
                        (skipprevnode.title === null) ||
                        (scoes_nav[launch_sco].hideprevious === 1)));

            svasu_buttons[1].set('disabled', ((prevnode === null) ||
                        (typeof(prevnode.scoid) === 'undefined') ||
                        (scoes_nav[prevnode.scoid].isvisible === "false") ||
                        (prevnode.title === null) ||
                        (scoes_nav[launch_sco].hideprevious === 1)));

            svasu_buttons[2].set('disabled', (upnode === null) ||
                        (typeof(upnode.scoid) === 'undefined') ||
                        (scoes_nav[upnode.scoid].isvisible === "false") ||
                        (upnode.title === null));

            svasu_buttons[3].set('disabled', ((nextnode === null) ||
                        ((nextnode.title === null) && (scoes_nav[launch_sco].flow !== 1)) ||
                        (typeof(nextnode.scoid) === 'undefined') ||
                        (scoes_nav[nextnode.scoid].isvisible === "false") ||
                        (scoes_nav[launch_sco].hidecontinue === 1)));

            svasu_buttons[4].set('disabled', ((skipnextnode === null) ||
                        (skipnextnode.title === null) ||
                        (typeof(skipnextnode.scoid) === 'undefined') ||
                        (scoes_nav[skipnextnode.scoid].isvisible === "false") ||
                        scoes_nav[launch_sco].hidecontinue === 1));
        };

        var svasu_toggle_toc = function(windowresize) {
            var toc = Y.one('#svasu_toc');
            var svasu_content = Y.one('#svasu_content');
            var svasu_toc_toggle_btn = Y.one('#svasu_toc_toggle_btn');
            var toc_disabled = toc.hasClass('disabled');
            var disabled_by = toc.getAttribute('disabled-by');
            // Remove width element style from resize handle.
            toc.setStyle('width', null);
            svasu_content.setStyle('width', null);
            if (windowresize === true) {
                if (disabled_by === 'user') {
                    return;
                }
                var body = Y.one('body');
                if (body.get('winWidth') < collapsetocwinsize) {
                    toc.addClass(cssclasses.disabled)
                        .setAttribute('disabled-by', 'screen-size');
                    svasu_toc_toggle_btn.setHTML('&gt;')
                        .set('title', M.util.get_string('show', 'moodle'));
                    svasu_content.removeClass(cssclasses.svasu_grid_content_toc_visible)
                        .addClass(cssclasses.svasu_grid_content_toc_hidden);
                } else if (body.get('winWidth') > collapsetocwinsize) {
                    toc.removeClass(cssclasses.disabled)
                        .removeAttribute('disabled-by');
                    svasu_toc_toggle_btn.setHTML('&lt;')
                        .set('title', M.util.get_string('hide', 'moodle'));
                    svasu_content.removeClass(cssclasses.svasu_grid_content_toc_hidden)
                        .addClass(cssclasses.svasu_grid_content_toc_visible);
                }
                return;
            }
            if (toc_disabled) {
                toc.removeClass(cssclasses.disabled)
                    .removeAttribute('disabled-by');
                svasu_toc_toggle_btn.setHTML('&lt;')
                    .set('title', M.util.get_string('hide', 'moodle'));
                svasu_content.removeClass(cssclasses.svasu_grid_content_toc_hidden)
                    .addClass(cssclasses.svasu_grid_content_toc_visible);
            } else {
                toc.addClass(cssclasses.disabled)
                    .setAttribute('disabled-by', 'user');
                svasu_toc_toggle_btn.setHTML('&gt;')
                    .set('title', M.util.get_string('show', 'moodle'));
                svasu_content.removeClass(cssclasses.svasu_grid_content_toc_visible)
                    .addClass(cssclasses.svasu_grid_content_toc_hidden);
            }
        };

        var svasu_resize_layout = function() {
            if (window_name) {
                return;
            }

            // make sure that the max width of the TOC doesn't go to far

            var svasu_toc_node = Y.one('#svasu_toc');
            var maxwidth = parseInt(Y.one('#svasu_layout').getComputedStyle('width'), 10);
            svasu_toc_node.setStyle('maxWidth', (maxwidth - 200));
            var cwidth = parseInt(svasu_toc_node.getComputedStyle('width'), 10);
            if (cwidth > (maxwidth - 1)) {
                svasu_toc_node.setStyle('width', (maxwidth - 50));
            }

            // Calculate the rough new height from the viewport height.
            var newheight = Y.one('body').get('winHeight') - 5
                - Y.one('#svasu_layout').getY()
                - window.pageYOffset;
            if (newheight < 680 || isNaN(newheight)) {
                newheight = 680;
            }
            Y.one('#svasu_layout').setStyle('height', newheight);

        };

        // Handle AJAX Request
        var svasu_ajax_request = function(url, datastring) {
            var myRequest = NewHttpReq();
            var result = DoRequest(myRequest, url + datastring);
            return result;
        };

        var svasu_up = function(node, update_launch_sco) {
            if (node.parent && node.parent.parent && typeof scoes_nav[launch_sco].parentscoid !== 'undefined') {
                var parentscoid = scoes_nav[launch_sco].parentscoid;
                var parent = node.parent;
                if (parent.title !== scoes_nav[parentscoid].url) {
                    parent = svasu_tree_node.getNodeByAttribute('title', scoes_nav[parentscoid].url);
                    if (parent === null) {
                        parent = svasu_tree_node.rootNode.children[0];
                        parent.title = scoes_nav[parentscoid].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = parentscoid;
                }
                return parent;
            }
            return null;
        };

        var svasu_lastchild = function(node) {
            if (node.children.length) {
                return svasu_lastchild(node.children[node.children.length - 1]);
            } else {
                return node;
            }
        };

        var svasu_prev = function(node, update_launch_sco) {
            if (node.previous() && node.previous().children.length &&
                    typeof scoes_nav[launch_sco].prevscoid !== 'undefined') {
                node = svasu_lastchild(node.previous());
                if (node) {
                    var prevscoid = scoes_nav[launch_sco].prevscoid;
                    if (node.title !== scoes_nav[prevscoid].url) {
                        node = svasu_tree_node.getNodeByAttribute('title', scoes_nav[prevscoid].url);
                        if (node === null) {
                            node = svasu_tree_node.rootNode.children[0];
                            node.title = scoes_nav[prevscoid].url;
                        }
                    }
                    if (update_launch_sco) {
                        launch_sco = prevscoid;
                    }
                    return node;
                } else {
                    return null;
                }
            }
            return svasu_skipprev(node, update_launch_sco);
        };

        var svasu_skipprev = function(node, update_launch_sco) {
            if (node.previous() && typeof scoes_nav[launch_sco].prevsibling !== 'undefined') {
                var prevsibling = scoes_nav[launch_sco].prevsibling;
                var previous = node.previous();
                var prevscoid = scoes_nav[launch_sco].prevscoid;
                if (previous.title !== scoes_nav[prevscoid].url) {
                    previous = svasu_tree_node.getNodeByAttribute('title', scoes_nav[prevsibling].url);
                    if (previous === null) {
                        previous = svasu_tree_node.rootNode.children[0];
                        previous.title = scoes_nav[prevsibling].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = prevsibling;
                }
                return previous;
            } else if (node.parent && node.parent.parent && typeof scoes_nav[launch_sco].parentscoid !== 'undefined') {
                var parentscoid = scoes_nav[launch_sco].parentscoid;
                var parent = node.parent;
                if (parent.title !== scoes_nav[parentscoid].url) {
                    parent = svasu_tree_node.getNodeByAttribute('title', scoes_nav[parentscoid].url);
                    if (parent === null) {
                        parent = svasu_tree_node.rootNode.children[0];
                        parent.title = scoes_nav[parentscoid].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = parentscoid;
                }
                return parent;
            }
            return null;
        };

        var svasu_next = function(node, update_launch_sco) {
            if (node === false) {
                return svasu_tree_node.children[0];
            }
            if (node.children.length && typeof scoes_nav[launch_sco].nextscoid != 'undefined') {
                node = node.children[0];
                var nextscoid = scoes_nav[launch_sco].nextscoid;
                if (node.title !== scoes_nav[nextscoid].url) {
                    node = svasu_tree_node.getNodeByAttribute('title', scoes_nav[nextscoid].url);
                    if (node === null) {
                        node = svasu_tree_node.rootNode.children[0];
                        node.title = scoes_nav[nextscoid].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = nextscoid;
                }
                return node;
            }
            return svasu_skipnext(node, update_launch_sco);
        };

        var svasu_skipnext = function(node, update_launch_sco) {
            var next = node.next();
            if (next && next.title && typeof scoes_nav[launch_sco] !== 'undefined' && typeof scoes_nav[launch_sco].nextsibling !== 'undefined') {
                var nextsibling = scoes_nav[launch_sco].nextsibling;
                if (next.title !== scoes_nav[nextsibling].url) {
                    next = svasu_tree_node.getNodeByAttribute('title', scoes_nav[nextsibling].url);
                    if (next === null) {
                        next = svasu_tree_node.rootNode.children[0];
                        next.title = scoes_nav[nextsibling].url;
                    }
                }
                if (update_launch_sco) {
                    launch_sco = nextsibling;
                }
                return next;
            } else if (node.parent && node.parent.parent && typeof scoes_nav[launch_sco].parentscoid !== 'undefined') {
                var parentscoid = scoes_nav[launch_sco].parentscoid;
                var parent = node.parent;
                if (parent.title !== scoes_nav[parentscoid].url) {
                    parent = svasu_tree_node.getNodeByAttribute('title', scoes_nav[parentscoid].url);
                    if (parent === null) {
                        parent = svasu_tree_node.rootNode.children[0];
                    }
                }
                if (update_launch_sco) {
                    launch_sco = parentscoid;
                }
                return svasu_skipnext(parent, update_launch_sco);
            }
            return null;
        };

        // Launch prev sco
        var svasu_launch_prev_sco = function() {
            var result = null;
            if (scoes_nav[launch_sco].flow === 1) {
                var datastring = scoes_nav[launch_sco].url + '&function=svasu_seq_flow&request=backward';
                result = svasu_ajax_request(M.cfg.wwwroot + '/mod/svasu/datamodels/sequencinghandler.php?', datastring);
                mod_svasu_seq = encodeURIComponent(result);
                result = Y.JSON.parse (result);
                if (typeof result.nextactivity.id != undefined) {
                        var node = svasu_prev(svasu_tree_node.getSelectedNodes()[0]);
                        if (node == null) {
                            // Avoid use of TreeView for Navigation.
                            node = svasu_tree_node.getSelectedNodes()[0];
                        }
                        if (node.title !== scoes_nav[result.nextactivity.id].url) {
                            node = svasu_tree_node.getNodeByAttribute('title', scoes_nav[result.nextactivity.id].url);
                            if (node === null) {
                                node = svasu_tree_node.rootNode.children[0];
                                node.title = scoes_nav[result.nextactivity.id].url;
                            }
                        }
                        launch_sco = result.nextactivity.id;
                        svasu_activate_item(node);
                        svasu_fixnav();
                } else {
                        svasu_activate_item(svasu_prev(svasu_tree_node.getSelectedNodes()[0], true));
                }
            } else {
                svasu_activate_item(svasu_prev(svasu_tree_node.getSelectedNodes()[0], true));
            }
        };

        // Launch next sco
        var svasu_launch_next_sco = function () {
            var result = null;
            if (scoes_nav[launch_sco].flow === 1) {
                var datastring = scoes_nav[launch_sco].url + '&function=svasu_seq_flow&request=forward';
                result = svasu_ajax_request(M.cfg.wwwroot + '/mod/svasu/datamodels/sequencinghandler.php?', datastring);
                mod_svasu_seq = encodeURIComponent(result);
                result = Y.JSON.parse (result);
                if (typeof result.nextactivity !== 'undefined' && typeof result.nextactivity.id !== 'undefined') {
                    var node = svasu_next(svasu_tree_node.getSelectedNodes()[0]);
                    if (node === null) {
                        // Avoid use of TreeView for Navigation.
                        node = svasu_tree_node.getSelectedNodes()[0];
                    }
                    node = svasu_tree_node.getNodeByAttribute('title', scoes_nav[result.nextactivity.id].url);
                    if (node === null) {
                        node = svasu_tree_node.rootNode.children[0];
                        node.title = scoes_nav[result.nextactivity.id].url;
                    }
                    launch_sco = result.nextactivity.id;
                    svasu_activate_item(node);
                    svasu_fixnav();
                } else {
                    svasu_activate_item(svasu_next(svasu_tree_node.getSelectedNodes()[0], true));
                }
            } else {
                svasu_activate_item(svasu_next(svasu_tree_node.getSelectedNodes()[0], true));
            }
        };

        mod_svasu_launch_prev_sco = svasu_launch_prev_sco;
        mod_svasu_launch_next_sco = svasu_launch_next_sco;

        var cssclasses = {
                // YUI grid class: use 100% of the available width to show only content, TOC hidden.
                svasu_grid_content_toc_hidden: 'yui3-u-1',
                // YUI grid class: use 1/5 of the available width to show TOC.
                svasu_grid_toc: 'yui3-u-1-5',
                // YUI grid class: use 1/24 of the available width to show TOC toggle button.
                svasu_grid_toggle: 'yui3-u-1-24',
                // YUI grid class: use 3/4 of the available width to show content, TOC visible.
                svasu_grid_content_toc_visible: 'yui3-u-3-4',
                // Reduce height of #svasu_object to accomodate nav buttons under content.
                svasu_nav_under_content: 'svasu_nav_under_content',
                disabled: 'disabled'
            };
        // layout
        Y.one('#svasu_toc_title').setHTML(toc_title);

        if (svasu_disable_toc) {
            Y.one('#svasu_toc').addClass(cssclasses.disabled);
            Y.one('#svasu_toc_toggle').addClass(cssclasses.disabled);
            Y.one('#svasu_content').addClass(cssclasses.svasu_grid_content_toc_hidden);
        } else {
            Y.one('#svasu_toc').addClass(cssclasses.svasu_grid_toc);
            Y.one('#svasu_toc_toggle').addClass(cssclasses.svasu_grid_toggle);
            Y.one('#svasu_toc_toggle_btn')
                .setHTML('&lt;')
                .setAttribute('title', M.util.get_string('hide', 'moodle'));
            Y.one('#svasu_content').addClass(cssclasses.svasu_grid_content_toc_visible);
            svasu_toggle_toc(true);
        }

        // hide the TOC if that is the default
        if (!svasu_disable_toc) {
            if (svasu_hide_toc == true) {
                Y.one('#svasu_toc').addClass(cssclasses.disabled);
                Y.one('#svasu_toc_toggle_btn')
                    .setHTML('&gt;')
                    .setAttribute('title', M.util.get_string('show', 'moodle'));
                Y.one('#svasu_content')
                    .removeClass(cssclasses.svasu_grid_content_toc_visible)
                    .addClass(cssclasses.svasu_grid_content_toc_hidden);
            }
        }

        // Basic initialization completed, show the elements.
        Y.one('#svasu_toc').removeClass('loading');
        Y.one('#svasu_toc_toggle').removeClass('loading');

        // TOC Resize handle.
        var layout_width = parseInt(Y.one('#svasu_layout').getComputedStyle('width'), 10);
        var svasu_resize_handle = new Y.Resize({
            node: '#svasu_toc',
            handles: 'r',
            defMinWidth: 0.2 * layout_width
        });
        // TOC tree
        var toc_source = Y.one('#svasu_tree > ul');
        var toc = svasu_parse_toc_tree(toc_source);
        // Empty container after parsing toc.
        var el = document.getElementById('svasu_tree');
        el.innerHTML = '';
        var tree = new Y.TreeView({
            container: '#svasu_tree',
            nodes: toc,
            multiSelect: false
        });
        svasu_tree_node = tree;
        // Trigger after instead of on, avoid recursive calls.
        tree.after('select', function(e) {
            var node = e.node;
            if (node.title == '' || node.title == null) {
                return; //this item has no navigation
            }

            // If item is already active, return; avoid recursive calls.
            if (obj = Y.one('#svasu_object')) {
                var svasu_active_url = obj.getAttribute('src');
                var node_full_url = M.cfg.wwwroot + '/mod/svasu/loadSCO.php?' + node.title;
                if (node_full_url === svasu_active_url) {
                    return;
                }
            } else if(svasu_current_node == node){
                return;
            }

            // Update launch_sco.
            if (typeof node.scoid !== 'undefined') {
                launch_sco = node.scoid;
            }
            svasu_activate_item(node);
            if (node.children.length) {
                svasu_bloody_labelclick = true;
            }
        });
        if (!svasu_disable_toc) {
            tree.on('close', function(e) {
                if (svasu_bloody_labelclick) {
                    svasu_bloody_labelclick = false;
                    return false;
                }
            });
            tree.subscribe('open', function(e) {
                if (svasu_bloody_labelclick) {
                    svasu_bloody_labelclick = false;
                    return false;
                }
            });
        }
        tree.render();
        tree.openAll();

        // On getting the window, always set the focus on the current item
        Y.one(Y.config.win).on('focus', function (e) {
            var current = svasu_tree_node.getSelectedNodes()[0];
            var toc_disabled = Y.one('#svasu_toc').hasClass('disabled');
            if (current.id && !toc_disabled) {
                Y.one('#' + current.id).focus();
            }
        });

        // navigation
        if (svasu_hide_nav == false) {
            // TODO: make some better&accessible buttons.
            var navbuttonshtml = '<span id="svasu_nav"><button id="nav_skipprev">&lt;&lt;</button>&nbsp;' +
                                    '<button id="nav_prev">&lt;</button>&nbsp;<button id="nav_up">^</button>&nbsp;' +
                                    '<button id="nav_next">&gt;</button>&nbsp;<button id="nav_skipnext">&gt;&gt;</button></span>';
            if (nav_display === 1) {
                Y.one('#svasu_navpanel').setHTML(navbuttonshtml);
            } else {
                // Nav panel is floating type.
                var navposition = null;
                if (navposition_left < 0 && navposition_top < 0) {
                    // Set default XY.
                    navposition = Y.one('#svasu_toc').getXY();
                    navposition[1] += 200;
                } else {
                    // Set user defined XY.
                    navposition = [];
                    navposition[0] = parseInt(navposition_left, 10);
                    navposition[1] = parseInt(navposition_top, 10);
                }
                svasu_nav_panel = new Y.Panel({
                    fillHeight: "body",
                    headerContent: M.util.get_string('navigation', 'svasu'),
                    visible: true,
                    xy: navposition,
                    zIndex: 999
                });
                svasu_nav_panel.set('bodyContent', navbuttonshtml);
                svasu_nav_panel.removeButton('close');
                svasu_nav_panel.plug(Y.Plugin.Drag, {handles: ['.yui3-widget-hd']});
                svasu_nav_panel.render();
            }

            svasu_buttons[0] = new Y.Button({
                srcNode: '#nav_skipprev',
                render: true,
                on: {
                        'click' : function(ev) {
                            svasu_activate_item(svasu_skipprev(svasu_tree_node.getSelectedNodes()[0], true));
                        },
                        'keydown' : function(ev) {
                            if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                                svasu_activate_item(svasu_skipprev(svasu_tree_node.getSelectedNodes()[0], true));
                            }
                        }
                    }
            });
            svasu_buttons[1] = new Y.Button({
                srcNode: '#nav_prev',
                render: true,
                on: {
                    'click' : function(ev) {
                        svasu_launch_prev_sco();
                    },
                    'keydown' : function(ev) {
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            svasu_launch_prev_sco();
                        }
                    }
                }
            });
            svasu_buttons[2] = new Y.Button({
                srcNode: '#nav_up',
                render: true,
                on: {
                    'click' : function(ev) {
                        svasu_activate_item(svasu_up(svasu_tree_node.getSelectedNodes()[0], true));
                    },
                    'keydown' : function(ev) {
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            svasu_activate_item(svasu_up(svasu_tree_node.getSelectedNodes()[0], true));
                        }
                    }
                }
            });
            svasu_buttons[3] = new Y.Button({
                srcNode: '#nav_next',
                render: true,
                on: {
                    'click' : function(ev) {
                        svasu_launch_next_sco();
                    },
                    'keydown' : function(ev) {
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            svasu_launch_next_sco();
                        }
                    }
                }
            });
            svasu_buttons[4] = new Y.Button({
                srcNode: '#nav_skipnext',
                render: true,
                on: {
                    'click' : function(ev) {
                        svasu_activate_item(svasu_skipnext(svasu_tree_node.getSelectedNodes()[0], true));
                    },
                    'keydown' : function(ev) {
                        if (ev.domEvent.keyCode === 13 || ev.domEvent.keyCode === 32) {
                            svasu_activate_item(svasu_skipnext(svasu_tree_node.getSelectedNodes()[0], true));
                        }
                    }
                }
            });
        }

        // finally activate the chosen item
        var svasu_first_url = null;
        if (typeof tree.rootNode.children[0] !== 'undefined') {
            if (tree.rootNode.children[0].title !== scoes_nav[launch_sco].url) {
                var node = tree.getNodeByAttribute('title', scoes_nav[launch_sco].url);
                if (node !== null) {
                    svasu_first_url = node;
                }
            } else {
                svasu_first_url = tree.rootNode.children[0];
            }
        }

        if (svasu_first_url == null) { // This is probably a single sco with no children (AICC Direct uses this).
            svasu_first_url = tree.rootNode;
        }
        svasu_first_url.title = scoes_nav[launch_sco].url;
        svasu_activate_item(svasu_first_url);

        // resizing
        svasu_resize_layout();

        // Collapse/expand TOC.
        Y.one('#svasu_toc_toggle').on('click', svasu_toggle_toc);
        Y.one('#svasu_toc_toggle').on('key', svasu_toggle_toc, 'down:enter,32');
        // fix layout if window resized
        Y.on("windowresize", function() {
            svasu_resize_layout();
            var toc_displayed = Y.one('#svasu_toc').getComputedStyle('display') !== 'none';
            if ((!svasu_disable_toc && !svasu_hide_toc) || toc_displayed) {
                svasu_toggle_toc(true);
            }
            // Set 20% as minWidth constrain of TOC.
            var layout_width = parseInt(Y.one('#svasu_layout').getComputedStyle('width'), 10);
            svasu_resize_handle.set('defMinWidth', 0.2 * layout_width);
        });
        // On resize drag, change width of svasu_content.
        svasu_resize_handle.on('resize:resize', function() {
            var tocwidth = parseInt(Y.one('#svasu_toc').getComputedStyle('width'), 10);
            var layoutwidth = parseInt(Y.one('#svasu_layout').getStyle('width'), 10);
            Y.one('#svasu_content').setStyle('width', (layoutwidth - tocwidth - 60));
        });
    });
};

M.mod_svasu.connectPrereqCallback = {

    success: function(id, o) {
        if (o.responseText !== undefined) {
            var snode = null,
                stitle = null;
            if (svasu_tree_node && o.responseText) {
                snode = svasu_tree_node.getSelectedNodes()[0];
                stitle = null;
                if (snode) {
                    stitle = snode.title;
                }
                // All gone with clear, add new root node.
                svasu_tree_node.clear(svasu_tree_node.createNode());
            }
            // Make sure the temporary tree element is not there.
            var el_old_tree = document.getElementById('svasutree123');
            if (el_old_tree) {
                el_old_tree.parentNode.removeChild(el_old_tree);
            }
            var el_new_tree = document.createElement('div');
            var pagecontent = document.getElementById("page-content");
            if (!pagecontent) {
                pagecontent = document.getElementById("content");
            }
            el_new_tree.setAttribute('id','svasutree123');
            el_new_tree.innerHTML = o.responseText;
            // Make sure it does not show.
            el_new_tree.style.display = 'none';
            pagecontent.appendChild(el_new_tree);
            // Ignore the first level element as this is the title.
            var startNode = el_new_tree.firstChild.firstChild;
            if (startNode.tagName == 'LI') {
                // Go back to the beginning.
                startNode = el_new_tree;
            }
            var toc_source = Y.one('#svasutree123 > ul');
            var toc = mod_svasu_parse_toc_tree(toc_source);
            svasu_tree_node.appendNode(svasu_tree_node.rootNode, toc);
            var el = document.getElementById('svasutree123');
            el.parentNode.removeChild(el);
            svasu_tree_node.render();
            svasu_tree_node.openAll();
            if (stitle !== null) {
                snode = svasu_tree_node.getNodeByAttribute('title', stitle);
                // Do not let destroyed node to be selected.
                if (snode && !snode.state.destroyed) {
                    snode.select();
                    var toc_disabled = Y.one('#svasu_toc').hasClass('disabled');
                    if (!toc_disabled) {
                        if (!snode.state.selected) {
                            snode.select();
                        }
                    }
                }
            }
        }
    },

    failure: function(id, o) {
        // TODO: do some sort of error handling.
    }

};
