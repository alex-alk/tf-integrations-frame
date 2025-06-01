$(document).ready(function() {
       
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
                            

                            jsontest = JSON.parse(`{
                                "response": {
                                    "plane~city|23~city|2393": {
                                    "Id": "plane~city|23~city|2393",
                                    "Content": {
                                        "Active": true
                                    },
                                    "From": {
                                        "City": {
                                        "Id": "23",
                                        "Name": "Bucuresti",
                                        "Country": {
                                            "Id": "176",
                                            "Code": "RO",
                                            "Name": "Romania"
                                        },
                                        "County": null
                                        }
                                    },
                                    "To": {
                                        "City": {
                                        "Id": "2393",
                                        "Name": "Paris",
                                        "Country": {
                                            "Id": "5",
                                            "Code": "FR",
                                            "Name": "Franta"
                                        },
                                        "County": null
                                        }
                                    },
                                    "TransportType": "plane",
                                    "Dates": {
                                        "2025-05-09": {
                                        "Date": "2025-05-09",
                                        "Nights": {
                                            "6": {
                                            "Nights": 6
                                            },
                                            "7": {
                                            "Nights": 7
                                            }
                                        }
                                        }
                                    }
                                    }
                                }
                                }`);

                            let obj = json.response;

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
    });