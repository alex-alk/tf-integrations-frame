import { dump } from './common.js';

const $submitBtn = $('#send');
const $form = $('#form');

// populate form from query params
const params = new URLSearchParams(window.location.search);

for (const p of params) {
    const value = p[1];

    const element = document.querySelector(`[name='${p[0]}']`);

    if (element.tagName === "INPUT" && element.type === "text") {
        element.value = value;
    } else if (element.tagName === "SELECT") {

        for (const option of element.options) {
            option.selected = (option.value === value);
        }
        element.dispatchEvent(new Event('change'));
    }
}


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
                error: function (xhr, error, code) {
                    $submitBtn.prop('disabled', false);
                    $formSpinner.toggleClass('sr-only');
                    $sendText.toggleClass('sr-only');
                }, 
                dataSrc: function (json) {
                    $submitBtn.prop('disabled', false);
                    $formSpinner.toggleClass('sr-only');
                    $sendText.toggleClass('sr-only');
                    
                    let obj = json.response;

                    let $toRequests = $('.q-expand-full-json-pre');

                    dump($toRequests, json['toRequestsAndResponses']);
                    $toRequests.show();

                    const response = Object.keys(obj).map((key) => obj[key]);

                    return response;
                },
                contentType: 'application/json',
            }
        });
    }
    
});

function getColumns() {
    const columns = [
        { data: 'DateStart' },
        { data: 'DateEnd' },
        { data: 'Price' },
        { data: 'Currency.Code' }
    ]

    return columns;
}

function getOrder() {
    return [];
}







