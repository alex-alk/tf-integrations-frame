import { dump } from './common.js';

const $submitBtn = $('#send');
const $form = $('#form');
const $formSpinner = $('#form-spinner');
const $sendText = $('#send-text');

let i = 0;
let table = null;
$submitBtn.click(function() {

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

                    let obj = json.response;

                    let $toRequests = $('.q-expand-full-json-pre');

                    dump($toRequests, json['toRequestsAndResponses']);
                    $toRequests.show();

                    const response = Object.keys(obj).map((key) => obj[key]);

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
    const columns = [
        { data: 'Id' },
        { data: 'Name' },
        { data: 'Country.Name' }
    ]

    return columns;
}

function getOrder() {
    return [];
}

function getTableHeader(call) {

    const header = '<td></td>' +
        '<td></td>' +
        '<td></td>' +
        '<th>Id</th>' +
        '<th>Name</th>' + 
        '<th>Country</th>';
    
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

    row = '<td>' + $i + '.</td>' +
        "<td><a href='javascript: //' class='q-result-expand-item'>[+]</a></td>" +
        "<td><a href='' target='_blank' class='q-result-query-item'>[&nearr;]</a></td>" +
        '<td>' + element.Id + '</td>' +
        '<td>' + element.Name + '</td>';

    return row;
}







