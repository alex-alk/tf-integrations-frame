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
        { data: 'Name' }
    ]

    return columns;
}

function getOrder() {
    return [];
}







