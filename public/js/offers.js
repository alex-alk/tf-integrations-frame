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
        { data: 'Id' },
        { data: 'Name' },
        {
            data: null, // No direct mapping, because it's custom
            render: function (data, type, row, meta) {
                // Combine multiple fields or generate HTML
                let rooms = '';
                for (const offer of row['Offers']) {
                    rooms += offer['Rooms'][0]['Merch']['Title'] + '<br>';
                }
                rooms = rooms.replace(/<br>$/, '');

                return rooms;
            }
        },
        {
            data: null, // No direct mapping, because it's custom
            render: function (data, type, row, meta) {
                // Combine multiple fields or generate HTML

                const post = $form.serializeControls();

                let pols = '';
                for (const offer of row['Offers']) {
                    const query = `?args[0][Hotel][InTourOperatorId]=${row.Id}` +
                        `&args[0][CheckIn]=${offer['Rooms'][0]['CheckinAfter']}` +
                        `&args[0][CheckOut]=${offer['Rooms'][0]['CheckinBefore']}` +
                        `&args[0][OriginalOffer][Gross]=${offer['Gross']}` +
                        `&args[0][SuppliedCurrency]=${offer['Currency']['Code']}` +
                        `&args[0][SuppliedPrice]=${offer['Gross']}` +
                        `&args[0][Rooms][0][adults]=${post['args'][0]['rooms'][0]['adults']}` +
                        `&args[0][OriginalOffer][Rooms][0][Id]=${offer['Rooms'][0]['Id']}` +
                        `&args[0][OriginalOffer][MealItem][Merch][Id]=${offer['MealItem']['Merch']['Id']}` +
                        `&args[0][Duration]=${post['args'][0]['days']}` +
                        `&to[Handle]=${post['to']['Handle']}` +
                        `&to[System_Software]=${post['to']['System_Software']}` +
                        `&args[0][OriginalOffer][bookingDataJson]=${htmlspecialchars(offer['bookingDataJson'] ?? '')}`;
                    // const cp = `cancel?
                    //     args[0][Hotel][InTourOperatorId]="${row.Id}"`;
                    // const up = `payment-plans?
                    //     args[0][Hotel][InTourOperatorId]="${row.Id}"`;
                    //pols += offer['Rooms'][0]['Merch']['Title'] + '<br>';
                    const polsRow = `<a href="payment-plans${query}" 
                        target="_blank" class="q-result-query-item">[PP]</a> <a href="cancel-fees${query}" 
                        target="_blank" class="q-result-query-item">[CP]</a> <a href="update-price${query}" 
                        target="_blank" class="q-result-query-item">[UP]</a><br>`;
                    pols += polsRow;
                }
                pols = pols.replace(/<br>$/, '');



                // $linkCp = env('APP_FOLDER') . 
                //         '/?call=cancellation-fees&hotelId='.$item['Id'].
                //         '&checkIn='. $offer['Rooms'][0]['CheckinAfter'] .
                //         '&checkOut='. $offer['Rooms'][0]['CheckinBefore'] .
                //         '&suppliedPrice='.$offer['Gross'].
                //         '&suppliedCurrency='.$offer['Currency']['Code'] . 
                //         '&adults='.$_POST['args'][0]['rooms'][0]['adults'].
                //         '&roomId='.$offer['Rooms'][0]['Id'].
                //         '&mealId='.$offer['MealItem']['Merch']['Id'].
                //         '&days='.$_POST['args'][0]['days'].
                //         '&handle='.$_POST['to']['Handle'].
                //         '&system='.$_POST['to']['System_Software'].
                //         '&bookingDataJson='.htmlspecialchars($offer['bookingDataJson'] ?? '');

                return pols;
            }
      }
    ]

    return columns;
}

function getOrder() {
    return [];
}

function htmlspecialchars(str) {
  if (typeof str !== 'string') return str;

  return str
    .replace(/&/g, '&amp;')  // Must be first
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}







