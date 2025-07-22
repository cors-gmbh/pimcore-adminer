<?php

declare(strict_types=1);

/*
 * CORS GmbH
 *
 * This source file is available under the MIT license
 *
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) CORS GmbH (https://www.cors.gmbh)
 * @license    https://www.cors.gmbh/license MIT
 *
 */

namespace CORS\Bundle\AdminerBundle\lib\Pim;

class AdminerPlugins
{
    public function head()
    {
        echo script('verifyVersion = function () {};');
        /** @see https://github.com/stano/adminer-floatThead */
        echo '<script' . nonce() . ' src="' . h('https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js') . '"></script>';
        echo '<script' . nonce() . ' src="' . h('https://cdnjs.cloudflare.com/ajax/libs/floatthead/2.0.3/jquery.floatThead.min.js') . '"></script>';
        echo '<script' . nonce() . '>$(document).ready(function() { $(\'#content table\').first().floatThead(); });</script>';
        echo '<style type="text/css">.floatThead-container { overflow: visible !important; }</style>';

        /** @see https://gist.github.com/scr4bble/9ee4a9f1405ffc1465f59e03768e2768 */
        echo script(
            <<<EOT
document.addEventListener('DOMContentLoaded', function(event) {
	var date = new Date();
	var tds = document.querySelectorAll('td[id^="val"]');
	for (var i = 0; i < tds.length; i++) {
		var text = tds[i].innerHTML.trim();
		if (text.match(/^\d{10}$/)) {
			date.setTime(parseInt(text) * 1000);
			tds[i].oldDate = text;
			// tds[i].newDate = date.toUTCString().substr(5); // UTC format
			tds[i].newDate = date.toLocaleString();	// Local format
			// tds[i].newDate = date.toLocaleFormat('%e %b %Y %H:%M:%S'); // Custom format - works in Firefox only
			tds[i].newDate = '<span title="' + tds[i].newDate + '">' + text + '</span>';
			tds[i].innerHTML = tds[i].newDate;
			tds[i].dateIsNew = true;
			tds[i].addEventListener('click', function(event) {
				this.innerHTML = (this.dateIsNew ? this.oldDate : this.newDate);
				this.dateIsNew = !this.dateIsNew;
			});
		}
	}
});
EOT
        );

        /** @see https://gist.github.com/NoxArt/8085521 */
        echo script(
            <<<EOT
		(function(){
			var executed = false;
			var saveAndRestore = function() {
				if (executed) {
					return;
				}

				executed = true;
				var menu = document.getElementById('menu');
				var scrolled = localStorage.getItem('_adminerScrolled');
				if (scrolled && scrolled >= 0) {
					setTimeout(function() {
						menu.scrollTop = scrolled;
					}, 50);
				}

				window.addEventListener('unload', function(){
					localStorage.setItem('_adminerScrolled', menu.scrollTop);
				});
			};
			document.addEventListener && document.addEventListener('DOMContentLoaded', saveAndRestore);
			document.attachEvent && document.attachEvent('onreadystatechange', saveAndRestore);
		})();
EOT
        );

        if (!isset($_GET['sql'])) {
            return;
        }

        $suggests = [
    '___mysql___' => [
        'DELETE FROM',
        'DISTINCT',
        'EXPLAIN',
        'FROM',
        'GROUP BY',
        'HAVING',
        'INSERT INTO',
        'INNER JOIN',
        'IGNORE',
        'LIMIT',
        'LEFT JOIN',
        'NULL',
        'ORDER BY',
        'ON DUPLICATE KEY UPDATE',
        'SELECT',
        'UPDATE',
        'WHERE',
    ],
];

        foreach (array_keys(tables_list()) as $table) {
            $suggests['___tables___'][] = $table;
            foreach (fields($table) as $field => $foo) {
                $suggests[$table][] = $field;
            }
        }

        echo <<<EOT
<style>
    #suggest_tablefields_container {
        min-width: 200px;
        margin: 0;
        padding: 0;
        overflow-y: auto;
        position: absolute;
        background-color: #fff;
    }

    #suggest_tablefields {
        list-style: none;
    }

    #suggest_tablefields dt {
        font-weight: bold;
    }

    #suggest_tablefields dd {
        margin: 0;
    }

    #suggest_tablefields dd strong {
        background-color: #ff0;
    }

    #suggest_search {
        width: 110px;
    }

    .noselect {
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    .xborder {
        border: 1px inset rgb(204, 204, 204);
    }

    /*textarea.sqlarea {display: block!important;}*/
</style>
EOT;

        echo script(
            <<<EOT
    function domReady (fn) {
        document.addEventListener("DOMContentLoaded", fn)
        if (document.readyState === "interactive" || document.readyState === "complete") {
            fn()
        }
    }

    function insertNodeAtCaret (node) {
        if (typeof window.getSelection != "undefined") {
            var sel = window.getSelection()
            if (sel.rangeCount) {
                var range = sel.getRangeAt(0)
                range.collapse(false)
                range.insertNode(node)
                range = range.cloneRange()
                range.selectNodeContents(node)
                range.collapse(false)
                sel.removeAllRanges()
                sel.addRange(range)
            }
        } else if (typeof document.selection != "undefined" && document.selection.type != "Control") {
            var html = (node.nodeType == 1) ? node.outerHTML : node.data
            var id = "marker_" + ("" + Math.random()).slice(2)
            html += '<span id="' + id + '"></span>'
            var textRange = document.selection.createRange()
            textRange.collapse(false)
            textRange.pasteHTML(html)
            var markerSpan = document.getElementById(id)
            textRange.moveToElementText(markerSpan)
            textRange.select()
            markerSpan.parentNode.removeChild(markerSpan)
        }
    }

    function getTable (suggests, tableName) {
        var table = "<dt>" + tableName + "</dt>"
        for (var k in suggests[tableName]) {
            table += "<dd><a href='#' data-text='" + tableName + "`.`" + suggests[tableName][k] + "'>" + suggests[tableName][k] + "</a></dd>"
        }
        return table
    }

    function compile (data) {
        document.getElementById('suggest_tablefields').innerHTML = data
        document.getElementById('suggest_search').value = '';
        //console.log(data)
    }

    domReady(() => {
        const suggests = JSON.parse('
EOT
    . json_encode($suggests) . <<<EOT
');
        const form = document.getElementById('form')
        const sqlarea = document.getElementsByClassName('sqlarea')[0]
        form.style.position = "relative"

        var suggests_mysql = ""

        suggests_mysql += "<dt>
EOT
    . lang('Tables') . <<<EOT
        </dt>"
        for (var k in suggests['___tables___']) {
            suggests_mysql += "<dd><a href='#' data-table='1'>" + suggests['___tables___'][k] + "</a></dd>"
        }
        suggests_mysql += "<dt>
EOT
    . lang('SQL command') . <<<EOT
</dt>"
        for (var k in suggests['___mysql___']) {
            suggests_mysql += "<dd><a href='#' data-nobt='1'>" + suggests['___mysql___'][k] + "</a></dd>"
        }

        var posLeft = (sqlarea.offsetWidth + 3)
        form.insertAdjacentHTML('afterbegin',
            '<div id="suggest_tablefields_container" style="height:' + sqlarea.offsetHeight + 'px;top:0;left:' + posLeft + 'px">' +
            '<input autocomplete="off" id="suggest_search" type="text" placeholder="
EOT
    . lang('Search') . <<<EOT
..."/><dl id="suggest_tablefields" class="noselect"></dl></div>')
        compile(suggests_mysql)

        document.addEventListener('click', function (event) {
            if (event.target.getAttribute('id') === 'suggest_search') {
                return
            }
            if (event.target.matches('.jush-custom')) {
                var table = getTable(suggests, event.target.textContent)
                compile(table)
                return
            }

            if (!event.target.matches('#suggest_tablefields') && !event.target.matches('a') && !event.target.matches('strong') && !event.target.matches('.sqlarea') && !event.target.matches('.jush-sql_code') && !event.target.matches('.jush-bac') && !event.target.matches('.jush-op')) {
                compile(suggests_mysql)
                return
            }

        }, false)

        document.getElementById('suggest_tablefields').addEventListener('click', function (event) {
            if (event.target.matches('a') || event.target.matches('strong')) {
                var target, text, bt = "`"
                if (event.target.matches('strong')) {
                    target = event.target = event.target.parentElement
                } else {
                    target = event.target
                }

                text = target.textContent
                sqlarea.focus()

                if (target.getAttribute("data-text")) {
                    text = target.getAttribute("data-text")
                }
                if (target.getAttribute("data-nobt")) {
                    bt = ""
                }

                insertNodeAtCaret(document.createTextNode(bt + text + bt + " "))

                if (target.getAttribute("data-table")) {
                    var table = getTable(suggests, target.textContent)
                    compile(table)
                }

                sqlarea.dispatchEvent(new KeyboardEvent('keyup'))
            }
        }, false)

        document.getElementById('suggest_search').addEventListener('keyup', function () {
            var value = this.value.toLowerCase()

            if (value != '') {
                var reg = (value + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, '\\$1')
                reg = new RegExp('(' + reg + ')', 'gi')
            }

            var tables = qsa('dd a', qs('#suggest_tablefields'))
            for (var i = 0; i < tables.length; i++) {
                var a = tables[i]
                var text = tables[i].textContent
                if (value == '') {
                    tables[i].className = ''
                    a.innerHTML = text
                } else {
                    tables[i].className = (text.toLowerCase().indexOf(value) == -1 ? 'hidden' : '')
                    a.innerHTML = text.replace(reg, '<strong>$1</strong>')
                }
            }

        }, false)
    })
EOT
        );
    }

    public function tablesPrint($tables)
    {
        echo script(
            <<<EOT
            var tablesFilterTimeout = null;
            var tablesFilterValue = '';

            function tablesFilter () {
                var value = qs('#filter-field').value.toLowerCase();
                if (value == tablesFilterValue) {
                    return;
                }
                tablesFilterValue = value;
                if (value != '') {
                    var reg = (value + '').replace(/([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g, '\\$1');
                    reg = new RegExp('(' + reg + ')', 'gi');
                }
                if (sessionStorage) {
                    sessionStorage.setItem('adminer_tables_filter', value);
                }
                var tables = qsa('li', qs('#tables'));
                for (var i = 0; i < tables.length; i++) {
                    var a = null;
                    var text = tables[i].getAttribute('data-table-name');
                    if (text == null) {
                        a = qsa('a', tables[i])[1];
                        text = a.innerHTML.trim();

                        tables[i].setAttribute('data-table-name', text);
                        a.setAttribute('data-link', 'main');
                    } else {
                        a = qs('a[data-link="main"]', tables[i]);
                    }
                    if (value == '') {
                        tables[i].className = '';
                        a.innerHTML = text;
                    } else {
                        tables[i].className = (text.toLowerCase().indexOf(value) == -1 ? 'hidden' : '');
                        a.innerHTML = text.replace(reg, '<strong>$1</strong>');
                    }
                }
            }

            function tablesFilterInput () {
                window.clearTimeout(tablesFilterTimeout);
                tablesFilterTimeout = window.setTimeout(tablesFilter, 200);
            }

            sessionStorage && document.addEventListener('DOMContentLoaded', function () {
                qs('#filter-field').oninput = tablesFilterInput;
            
                var db = qs('#dbs').querySelector('select');
                db = db.options[db.selectedIndex].text;
                if (db == sessionStorage.getItem('adminer_tables_filter_db') && sessionStorage.getItem('adminer_tables_filter')) {
                    qs('#filter-field').value = sessionStorage.getItem('adminer_tables_filter');
                    tablesFilter();
                }
                sessionStorage.setItem('adminer_tables_filter_db', db);
            });
        </script>
        <p class="jsonly"><input id="filter-field" autocomplete="off" placeholder="
EOT
            . lang('Search') . <<<EOT
">
EOT
        );
    }
}
