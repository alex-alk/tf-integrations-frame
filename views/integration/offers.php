<?php $scripts['countries'] = 'offers.js' ?>

<?php require __DIR__ . '/../common/head.php' ?>

<body>
    <?php require __DIR__ . '/../common/header.php' ?>
    <div class="container-fluid" style="padding-top: 20px;">
        <div class="row flex-xl-nowrap">
            <div class="col-md-3 col-xl-2" style="padding-top: 20px;">
                <?php require __DIR__ . '/../common/menu.php' ?>
            </div>
            <main class="col-md-9 col-xl-10 py-md-3 pl-md-5 bd-content" role="main">
                <h3 class="bd-title" id="content">Search - Availability</h3>
                <div>
                    <pre class="">This service provides the list of availabilities.</pre>
                </div>
                <br>
                <form id="form" action="" method="POST">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>Parameter Name</th>
                                <th>Test Value</th>
                                <th>Info</th>
                            </tr>


                            <tr>
                                <td>
                                    to[ApiUsername]
                                </td>

                                <td>

                                    <input type="text" name="to[ApiUsername]">
                                </td>
                                <td>A user account identifier. </td>
                            </tr>

                            <tr>
                                <td>
                                    to[ApiPassword]
                                </td>

                                <td>

                                    <input type="text" name="to[ApiPassword]">
                                </td>
                                <td>A user account password. </td>
                            </tr>

                            <tr>
                                <td>
                                    to[ApiContext]
                                </td>

                                <td>

                                    <input type="text" name="to[ApiContext]">
                                </td>
                                <td>Anything extra. </td>
                            </tr>

                            <tr>
                                <td>
                                    to[ApiCode]
                                </td>

                                <td>

                                    <input type="text" name="to[ApiCode]">
                                </td>
                                <td>Anything extra. </td>
                            </tr>

                            <tr>
                                <td>
                                    to[System_Software]
                                </td>

                                <td>


                                    <select name="to[System_Software]">
                                        <option value="infinitehotel">infinitehotel</option>
                                        <option value="cyberlogic">cyberlogic</option>
                                        <option value="onetourismo">onetourismo</option>
                                        <option value="h2b_software">h2b_software</option>
                                        <option value="alladyn-hotels">alladyn-hotels</option>
                                        <option value="alladyn-charters">alladyn-charters</option>
                                        <option value="apitude">apitude</option>
                                        <option value="eurosite">eurosite</option>
                                        <option value="amara">amara</option>
                                        <option value="etrip-agency">etrip-agency</option>
                                        <option value="brostravel">brostravel</option>
                                        <option value="odeon">odeon</option>
                                        <option value="beapi">beapi</option>
                                        <option value="etrip">etrip</option>
                                        <option value="travelio">travelio</option>
                                        <option value="travelio_v2">travelio_v2</option>
                                        <option value="tourvisio">tourvisio</option>
                                        <option value="tourvisio_v2">tourvisio_v2</option>
                                        <option value="alladyn_old">alladyn_old</option>
                                        <option value="goglobal">goglobal</option>
                                        <option value="goglobal_v2">goglobal_v2</option>
                                        <option value="megatec">megatec</option>
                                        <option value="sejour">sejour</option>
                                        <option value="sansejour">sansejour</option>
                                        <option value="teztour_v2">teztour_v2</option>
                                        <option value="calypso">calypso</option>
                                        <option value="samo">samo</option>
                                        <option value="tbo">tbo</option>
                                        <option value="sphinx">sphinx</option>
                                        <option value="hotelcon">hotelcon</option>
                                        <option value="etg">etg</option>
                                        <option value="irix">irix</option>
                                        <option value="anex">anex</option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    to[Handle]
                                </td>

                                <td>


                                    <select name="to[Handle]">
                                        <option value="infinitehotel-demo">infinitehotel-demo</option>
                                        <option value="infinitehotel">infinitehotel</option>
                                        <option value="localhost-infinitehotel">localhost-infinitehotel</option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    to[ApiUrl]
                                </td>

                                <td>


                                    <select name="to[ApiUrl]">
                                        <option value="https://uatapi.infinitehotel.com/gekko-front/ws/v2_4">https://uatapi.infinitehotel.com/gekko-front/ws/v2_4</option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    to[getLatestCache]
                                </td>

                                <td>


                                    <select name="to[getLatestCache]">

                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    to[skipTopCache]
                                </td>

                                <td>


                                    <select name="to[skipTopCache]">

                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    to[renewTopCache]
                                </td>

                                <td>


                                    <select name="to[renewTopCache]">

                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    to[clearDownloads]
                                </td>

                                <td>


                                    <select name="to[clearDownloads]">

                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>

                                </td>
                                <td>Clears TO downloads folder before execution. </td>
                            </tr>

                            <tr>
                                <td>
                                    method
                                </td>

                                <td>
                                    <select name="method">
                                        <option value="api_getOffers" selected="true">api_getOffers</option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr style="display: none">
                                <td>
                                    get-raw-data
                                </td>

                                <td>
                                    <input type="hidden" name="get-to-requests" value="1">
                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][bigFile]
                                </td>

                                <td>


                                    <select name="args[0][bigFile]">

                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>

                                </td>
                                <td>Search offers in the main file. </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][showHotelName]
                                </td>

                                <td>


                                    <select name="args[0][showHotelName]">

                                        <option value="true">true </option>
                                        <option value="false">false </option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][serviceTypes][0]
                                </td>

                                <td>


                                    <select name="args[0][serviceTypes][0]">

                                        <option value="hotel">hotel </option>
                                        <option value="charter">charter </option>
                                        <option value="tour">tour </option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][transportTypes][0]
                                </td>

                                <td>


                                    <select name="args[0][transportTypes][0]">

                                        <option value="plane">plane </option>
                                        <option value="bus">bus </option>
                                    </select>

                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][checkIn] *
                                </td>

                                <td>

                                    <input type="text" name="args[0][checkIn]" value="2025-09-01">
                                </td>
                                <td>*mandatory. Date of check-in or departure for charters (ex: 2023-02-01). </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][days]
                                </td>

                                <td>

                                    <input type="text" name="args[0][days]" value="7">
                                </td>
                                <td>7 </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][checkOut]
                                </td>

                                <td>

                                    <input type="text" name="args[0][checkOut]" value="2025-09-02">
                                </td>
                                <td>2025-06-08 </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][RequestCurrency]
                                </td>

                                <td>

                                    <input type="text" name="args[0][RequestCurrency]" value="EUR">
                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][price_filter][from]
                                </td>

                                <td>

                                    <input type="text" name="args[0][price_filter][from]">
                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][price_filter][to]
                                </td>

                                <td>

                                    <input type="text" name="args[0][price_filter][to]">
                                </td>
                                <td> </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][countryId]
                                </td>

                                <td>

                                    <input type="text" name="args[0][countryId]" value="">
                                </td>
                                <td>Country code. </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][cityId]
                                </td>

                                <td>

                                    <input type="text" name="args[0][cityId]" value="68">
                                </td>
                                <td>The city’s code. </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][departureCity]
                                </td>

                                <td>

                                    <input type="text" name="args[0][departureCity]" value="">
                                </td>
                                <td>Departure city ID (for charters). </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][departureCityId]
                                </td>

                                <td>

                                    <input type="text" name="args[0][departureCityId]">
                                </td>
                                <td>Departure city ID (for tours). </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][regionId]
                                </td>

                                <td>

                                    <input type="text" name="args[0][regionId]" value="">
                                </td>
                                <td>The region’s code. </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][travelItemId]
                                </td>

                                <td>

                                    <input type="text" name="args[0][travelItemId]" value="2029">
                                </td>
                                <td>Hotel Id. </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][rooms][0][adults] *
                                </td>

                                <td>

                                    <input type="text" name="args[0][rooms][0][adults]" value="2">
                                </td>
                                <td>*mandatory. The number of adults in the room. </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][rooms][0][children]
                                </td>

                                <td>

                                    <input type="text" name="args[0][rooms][0][children]">
                                </td>
                                <td>The number of children. (ex: 2) </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][rooms][0][childrenAges][0]
                                </td>

                                <td>

                                    <input type="text" name="args[0][rooms][0][childrenAges][0]">
                                </td>
                                <td>ex: 7 </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][rooms][0][childrenAges][1]
                                </td>

                                <td>

                                    <input type="text" name="args[0][rooms][0][childrenAges][1]">
                                </td>
                                <td>ex: 7 </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][rooms][0][childrenAges][2]
                                </td>

                                <td>

                                    <input type="text" name="args[0][rooms][0][childrenAges][2]">
                                </td>
                                <td>ex: 7 </td>
                            </tr>

                            <tr>
                                <td>
                                    args[0][rooms][0][childrenAges][3]
                                </td>

                                <td>
                                    <input type="text" name="args[0][rooms][0][childrenAges][3]">
                                </td>
                                <td>ex: 7 </td>
                            </tr>

                            <tr>
                                <td>
                                    json
                                </td>

                                <td>
                                    <textarea style="width: 100%;" name="json"></textarea>
                                </td>
                                <td>Use this instead of fields </td>
                            </tr>

                            <tr>
                                <td colspan="3">
                                    <button id="send" class="btn btn-outline-primary" type="button">
                                        <span id="form-spinner" class="spinner-border spinner-border-sm sr-only" role="status" aria-hidden="true"></span>
                                        <span id="send-text">Execute</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </form>

                <div class="mb-5">
                    <hr>
                    <div>
                        <a href="javascript://" class="q-expand-full-json">[+ View TO requests]</a>
                        <div class="q-expand-full-json-pre" style="display: none;">
                        </div>
                    </div>
                </div>

                <table class='table d-none' id="table">
                    <thead>
                        <tr>
                            <td>Id</td>
                            <td>Name</td>
                            <td>Rooms</td>
                            <td>PP/CP/UP</td>
                        </tr>

                    </thead>
                    <tbody>

                    </tbody>
                </table>

            </main>
        </div>
    </div>
</body>
<?php require __DIR__ . '/../common/footer.php' ?>