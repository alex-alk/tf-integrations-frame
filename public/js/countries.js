
$.fn.serializeControls = function() {
    var data = {};

    function buildInputObject(arr, val) {
        if (arr.length < 1) {
            return val;  
        }
        var objkey = arr[0];
        if (objkey.slice(-1) == "]") {
            objkey = objkey.slice(0,-1);
        }  
        var result = {};
        if (arr.length == 1){
            result[objkey] = val;
        } else {
        arr.shift();
        var nestedVal = buildInputObject(arr,val);
            result[objkey] = nestedVal;
        }
        return result;
    }

    $.each(this.serializeArray(), function() {
        var val = this.value;
        var c = this.name.split("[");
        var a = buildInputObject(c, val);
        $.extend(true, data, a);
    });
    
    return data;
}

const $submitBtn = $('#send');
const $form = $('#form');
const $formSpinner = $('#form-spinner');
const $sendText = $('#send-text');

let i = 0;
let table = null;
$submitBtn.click(function() {

    // jsontest = JSON.parse(`{
    //     "response": {
    //         "1": {
    //             "Id": "1",
    //             "Code" : "2",
    //             "Name" : "3"
    //         }
    //     },
    //     "toRequestsAndResponses" : [
    //         {
    //             "method" : "m",
    //             "url": "u",
    //             "body": "b",
    //             "headers" : "h"
    //         }, 
    //         {
    //             "method" : "m2",
    //             "url": "u2",
    //             "body": "b2",
    //             "headers" : "h2"
    //         }
    //     ]
    // }`);
    // console.log(jsontest);

    $submitBtn.prop('disabled', true);
    $formSpinner.toggleClass('sr-only');
    $sendText.toggleClass('sr-only');

    $('#table').removeClass('d-none');
    i++;
    if (i > 1) {
        table.ajax.reload();
    } else {
        table = new DataTable('#table', {
            columns: getColumns(),
            order: getOrder(),
            ajax: {
                url: '/public/api',
                type: 'POST',
                data: function ( data ) {
                    return JSON.stringify( $form.serializeControls() );
                },
                dataSrc: function (json) {
                    
                    // jsontest = JSON.parse(`{
                    //     "response": {
                    //         "plane~city|23~city|2393": {
                    //         "Id": "plane~city|23~city|2393",
                    //         "Content": {
                    //             "Active": true
                    //         },
                    //         "From": {
                    //             "City": {
                    //             "Id": "23",
                    //             "Name": "Bucuresti",
                    //             "Country": {
                    //                 "Id": "176",
                    //                 "Code": "RO",
                    //                 "Name": "Romania"
                    //             },
                    //             "County": null
                    //             }
                    //         },
                    //         "To": {
                    //             "City": {
                    //             "Id": "2393",
                    //             "Name": "Paris",
                    //             "Country": {
                    //                 "Id": "5",
                    //                 "Code": "FR",
                    //                 "Name": "Franta"
                    //             },
                    //             "County": null
                    //             }
                    //         },
                    //         "TransportType": "plane",
                    //         "Dates": {
                    //             "2025-05-09": {
                    //             "Date": "2025-05-09",
                    //             "Nights": {
                    //                 "6": {
                    //                 "Nights": 6
                    //                 },
                    //                 "7": {
                    //                 "Nights": 7
                    //                 }
                    //             }
                    //             }
                    //         }
                    //         }
                    //     }
                    //     }`);
                    

                    let obj = json.response;

                    function escapeHtml(str) {
                        return String(str)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;');
                    }

                    let $toRequests = $('.q-expand-full-json-pre');

                    dump($toRequests, json['toRequestsAndResponses']);
                    $toRequests.show();

                    response = Object.keys(obj).map((key) => obj[key]);

                    $formSpinner.toggleClass('sr-only');
                    $sendText.toggleClass('sr-only');
                    $submitBtn.prop('disabled', false);
                    return response;
                },
                contentType: 'application/json',
            }
        });
    }
    
});

function getColumns() {
    const call = 'countries';
    columns = [];

    switch (call) {
        case 'countries':
            columns = [
                { data: 'Id' },
                { data: 'Code' },
                { data: 'Name' }
            ];
            break;
        case 'cities':
            columns = [
                { data: 'Id' },
                { data: 'Name' },
                { data: 'County.Name' },
                { data: 'Country.Name' }
            ];
            break;
        case 'hotels':
            columns = [
                { data: 'Id' },
                { data: 'Name' },
                { data: 'Address.City.Name' },
                { data: 'Address.City.County.Name' },
                { data: 'Address.City.Country.Name' }
            ];
            break;
        case 'offers':
            columns = [
                { data: 'Id' }
            ];
            break;
        case 'availability-dates':
            columns = [
                { data: 'Id' },
                { data: 'From.City.Name' },
                { data: 'To.City.Name' },
                { data: 'To.City.County.Name' },
                { 
                    data: 'Dates' ,
                    mRender: function(data, type, row) {

                        let datesStr = '';
                        for (date in row.Dates) {
                            dateObj = row.Dates[date];
                            nightsStr = '';
                            for (night in dateObj.Nights) {
                                nightsStr += night + ' ';
                            }
                            datesStr += date + ': ' + nightsStr + '<br>';
                        }
                        datesStr.trimEnd();
                        return datesStr;

                    }
                }
            ];
            break;
    }

    return columns;
}

function getOrder() {
    const call = 'countries';
    let order = [];

    switch (call) {
        
        case 'availability-dates':
            order = [
                [1, 'asc']
            ];
        break;
    }

    return order;
}

function getTableHeader(call) {
    let header = '';
    switch (call) {
        case 'countries': 
            header = //'<td>N</td>' +
            //'<td></td>' +
            //'<td></td>' +
            '<td>Id</td>' +
            '<td>Code</td>' +
            '<td>Name</td>';
            break;
        case 'cities' :
            header = '<td></td>' +
            '<td></td>' +
            '<td></td>' +
            '<th>Id</th>' +
            '<th>Name</th>';
            break;
    }
    return header;
}

function createRows(table, data, call) {
    let $i = 0;

    for (const prop of Object.keys(data)) {
        element = data[prop];
        
        $i++;
        const tr = document.createElement('tr');

        tr.innerHTML = getFirstRow(element, call, $i)
        
        $table.append(tr);

        const trjs = document.createElement('tr');
        //trjs.style.display = 'none';
        //trjs.innerHTML = "<td colspan='6'><code>" + JSON.stringify(element) + "</code></td>";
        //$table.append(trjs);
    }
}

function getFirstRow(element, call, $i) {
    let row = '';
    switch (call) {
        case 'countries': 
            row = '<td>' + $i + '.</td>' +
            //"<td><a href='javascript: //' class='q-result-expand-item'>[+]</a></td>" +
            //"<td><a href='' target='_blank' class='q-result-query-item'>[&nearr;]</a></td>" +
            '<td>' + element.Id + '</td>' +
            '<td>' + element.Code + '</td>' +
            '<td>' + element.Name + '</td>';
            break;
        case 'cities' :
            row = '<td>' + $i + '.</td>' +
            "<td><a href='javascript: //' class='q-result-expand-item'>[+]</a></td>" +
            "<td><a href='' target='_blank' class='q-result-query-item'>[&nearr;]</a></td>" +
            '<td>' + element.Id + '</td>' +
            '<td>' + element.Name + '</td>';
            break;
    }
    return row;
}



function dump($container, ...args) {
    const container = document.createElement('div');
    container.style.fontFamily = 'monospace';
    container.style.fontSize = '12px';
    container.style.border = '2px dotted gray';
    container.style.margin = '10px';
    container.style.padding = '10px';

    // Track circular references
    const visited = new WeakSet();

    args.forEach(arg => {
        container.appendChild(dumpVar(arg, 0, visited));
    });

    $container.append(container);
}

function dumpVar(value, depth = 0, visited, key = '') {
    const wrapper = document.createElement('div');
    const indent = '&nbsp;'.repeat(depth * 4);

    const line = document.createElement('div');
    let lineContent =
        `${indent}<b>${escapeHTML(key)}</b>${key ? ': ' : ''}${escapeHTML(getType(value))}`;

    if (value !== null && typeof value === 'object') {
        if (visited.has(value)) {
            lineContent += ` <i>[Circular]</i>`;
            line.innerHTML = lineContent;
            wrapper.appendChild(line);
            return wrapper;
        }
        visited.add(value);

        const toggle = document.createElement('span');
        toggle.textContent = ' [+]';
        toggle.style.cursor = 'pointer';
        toggle.style.color = 'blue';

        const childrenContainer = document.createElement('div');
        childrenContainer.style.display = 'none';
        childrenContainer.style.marginLeft = '20px';

        toggle.addEventListener('click', () => {
            if (childrenContainer.style.display === 'none') {
                childrenContainer.style.display = 'block';
                toggle.textContent = ' [-]';
            } else {
                childrenContainer.style.display = 'none';
                toggle.textContent = ' [+]';
            }
        });

        line.innerHTML = lineContent;
        line.appendChild(toggle);
        wrapper.appendChild(line);
        wrapper.appendChild(childrenContainer);

        for (let prop in value) {
            if (Object.prototype.hasOwnProperty.call(value, prop)) {
                childrenContainer.appendChild(dumpVar(value[prop], depth + 1, visited, prop));
            }
        }
    } else {
        const formattedValue = formatPrimitive(value);
        lineContent +=
            ` <pre style="color: green; display: inline; margin: 0;">` +
            `${escapeHTML(formattedValue)}` +
            `</pre>`;
        line.innerHTML = lineContent;
        wrapper.appendChild(line);
    }

    return wrapper;
}

function formatPrimitive(val) {
    if (typeof val === 'string') {
        if (isLikelyXML(val)) {
            const pretty = prettyFormatXML(val);
            if (pretty !== null) {
                return pretty;
            }
        }
        // Fallback: show as quoted string
        return `"${val.replace(/\n/g, '\\n')}"`;
    }
    if (val === null) return 'null';
    if (val === undefined) return 'undefined';
    return val.toString();
}

function getType(val) {
    if (val === null) return '[null]';
    if (Array.isArray(val)) return `[array(${val.length})]`;
    if (typeof val === 'object') return `{...}`;
    return `[${typeof val}]`;
}

function escapeHTML(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function isLikelyXML(str) {
    return /^\s*<\?xml|^\s*<\w+/.test(str);
}

function prettyFormatXML(xmlString) {
    try {
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlString, 'application/xml');
        if (xmlDoc.getElementsByTagName('parsererror').length) {
            return null;
        }
        const serializer = new XMLSerializer();
        const raw = serializer.serializeToString(xmlDoc);
        return indentXML(raw);
    } catch {
        return null;
    }
}

function indentXML(xml) {
    const PADDING = '  '; // two spaces per level

    // 1) Insert line breaks between tags
    xml = xml.replace(/(>)(<)(\/*)/g, '$1\n$2$3');

    const lines = xml.split('\n');
    let pad = 0;
    let formatted = '';

    for (let rawLine of lines) {
        const line = rawLine.trim();
        if (!line) continue;

        // 2) If this is a closing tag, reduce indent BEFORE writing
        if (/^<\/[^>]+>/.test(line)) {
            pad = Math.max(pad - 1, 0);
        }

        // 3) Write the line with current indent
        formatted += PADDING.repeat(pad) + line + '\n';

        // 4) Decide if we should increase indent AFTER writing
        //
        // XML declaration (<?xml ...?>) should not affect indent:
        const isDeclaration = /^<\?.*\?>$/.test(line);
        //
        // Self‐closing tags (e.g. <meg:GetCountries/> or <tag attr="x"/>) should NOT indent:
        const isSelfClosing = /\/>$/.test(line);
        //
        // Opening‐only tags that do not have inline content or a closing on the same line:
        // We use ^<[^\/!?][^>]*>$ to detect “<tag ...>” with no inner text or closing.
        const isOpeningOnly = /^<[^\/!?][^>]*>$/.test(line) && !isSelfClosing && !isDeclaration;

        if (isOpeningOnly) {
            pad++;
        }
    }

    return formatted.trim();
}




