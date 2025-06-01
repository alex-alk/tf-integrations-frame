<?php $scripts['countries'] = 'countries.js' ?>

<?php require __DIR__ . '/../common/head.php' ?>

<body>
    <?php require __DIR__ . '/../common/header.php' ?>
    <div class="container-fluid" style="padding-top: 20px;">
        <div class="row flex-xl-nowrap">
            <div style="padding-top: 20px;">
                <?php require __DIR__ . '/../common/menu.php' ?>
            </div>
            <main class="col-md-9 col-xl-8 py-md-3 pl-md-5 bd-content" role="main">
                <h3 class="bd-title" id="content">Geography - Countries</h3>
                <div>
                    <pre class="q-expand-full-json-pre">This service provides the list of all the countries.</pre>
                </div>
                <br>
                <form id="form" action="/countries" method="POST">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>Parameter Name</th>
                                <th>Test Value</th>
                                <th>Info</th>
                            </tr>

                            <tr>
                                <td> to[ApiUsername] </td>
                                <td><input type="text" name="to[ApiUsername]"></td>
                                <td>A user account identifier. </td>
                            </tr>

                            <tr>
                                <td>to[ApiPassword]</td>
                                <td><input type="text" name="to[ApiPassword]"></td>
                                <td>A user account password. </td>
                            </tr>

                            <tr>
                                <td>to[ApiContext]</td>

                                <td><input type="text" name="to[ApiContext]"></td>
                                <td>Anything extra. </td>
                            </tr>

                            <tr>
                                <td>to[ApiCode]</td>
                                <td><input type="text" name="to[ApiCode]"></td>
                                <td>Anything extra. </td>
                            </tr>

                            <tr>
                                <td>to[System_Software]</td>
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
                                <td></td>
                            </tr>

                            <tr>
                                <td>to[Handle]</td>
                                <td>
                                    <select name="to[Handle]">
                                        <option value="infinitehotel-demo">infinitehotel-demo</option>
                                        <option value="infinitehotel">infinitehotel</option>
                                        <option value="localhost-infinitehotel">localhost-infinitehotel</option>
                                    </select>

                                </td>
                                <td></td>
                            </tr>

                            <tr>
                                <td>to[ApiUrl]</td>
                                <td>
                                    <select name="to[ApiUrl]">
                                        <option value="https://uatapi.infinitehotel.com/gekko-front/ws/v2_4">https://uatapi.infinitehotel.com/gekko-front/ws/v2_4</option>
                                    </select>
                                </td>
                                <td></td>
                            </tr>

                            <tr>
                                <td>to[getLatestCache]</td>
                                <td>
                                    <select name="to[getLatestCache]">

                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>
                                </td>
                                <td></td>
                            </tr>

                            <tr>
                                <td>to[skipTopCache]</td>
                                <td>
                                    <select name="to[skipTopCache]">
                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>

                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td>to[renewTopCache]</td>
                                <td>
                                    <select name="to[renewTopCache]">
                                        <option value="false">false </option>
                                        <option value="true">true </option>
                                    </select>
                                </td>
                                <td></td>
                            </tr>

                            <tr>
                                <td>method</td>
                                <td>
                                    <select name="method">
                                        <option value="api_getCountries" selected="true">api_getCountries</option>
                                        <option disabled="" value="api_getCities">api_getCities</option>
                                        <option disabled="" value="api_getRegions">api_getRegions</option>
                                        <option disabled="" value="api_getHotels">api_getHotels</option>
                                        <option disabled="" value="api_getHotelDetails">api_getHotelDetails</option>
                                        <option disabled="" value="api_getRoomTypes">api_getRoomTypes</option>
                                        <option disabled="" value="api_getOffers">api_getOffers</option>
                                        <option disabled="" value="api_doBooking">api_doBooking</option>
                                        <option disabled="" value="api_getAvailabilityDates">api_getAvailabilityDates</option>
                                        <option disabled="" value="api_getOfferCancelFees">api_getOfferCancelFees</option>
                                        <option disabled="" value="api_getOfferCancelFeesPaymentsAvailabilityAndPrice">api_getOfferCancelFeesPaymentsAvailabilityAndPrice</option>
                                        <option disabled="" value="api_getOfferPaymentsPlan">api_getOfferPaymentsPlan</option>
                                        <option disabled="" value="api_testConnection">api_testConnection</option>
                                        <option disabled="" value="api_getTours">api_getTours</option>
                                        <option disabled="" value="api_downloadOffers">api_downloadOffers</option>
                                        <option disabled="" value="cache_TOP_Data">cache_TOP_Data</option>
                                    </select>
                                </td>
                                <td> </td>
                            </tr>

                            <tr style="display: none">
                                <td>get-raw-data</td>
                                <td>
                                    <input type="hidden" name="get-raw-data" value="1">
                                </td>
                                <td></td>
                            </tr>

                            <tr>
                                <td colspan="3">
                                    <button id="send" class="btn btn-outline-primary" type="button">
                                        <span id="form-spinner" class="spinner-border spinner-border-sm sr-only" role="status" aria-hidden="true"></span>
                                        <span id="send-text">Test</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>


                <?php if (isset($responses) || isset($error)): ?>
                    <hr>
                    <div>
                        <a href="javascript://" class="q-expand-full-json">[+ View TO requests]</a>
                        <pre class="q-expand-full-json-pre" style="display: none;">
                            <?php dump($responses)?>
                        </pre>
                    </div>
                <?php endif ?>

                <?php if (!empty($error)): ?>

                    <hr>
                    <div style="color: red">Error</div>
                    <div><?php echo htmlspecialchars($error) ?></div>

                <?php elseif (isset($list)): ?>

                    <hr />

                    <h5>Result: 
                        <?php if(is_array($list)): ?>
                            <?php echo count($list) . ' rows in 10' ?> sec</h5>
                        <?php else: ?>
                            <?php echo '1 row in 10' ?> sec</h5>
                        <?php endif ?>
                    <hr />
                    <a href="javascript://" class="q-expand-full-json">[+ View short JSON ]</a>
                    <pre class="q-expand-full-json-pre" style="display: none;"><?php echo htmlspecialchars($shortJson) ?></pre>

                    <?php if (empty($error)): ?>
                        <table class="table q-results-table">
                            <tbody>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <th>Id</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                </tr>
                                <?php $i = 0;?>
                                <?php foreach ($list as $item): ?>
                                    <?php $i++ ?>
                                    <tr>
                                        <td><?php echo $i?>.</td>
                                        <td><a href="javascript: //" class="q-result-expand-item">[+]</a></td>
                                        <td><a href="<?php echo env('APP_FOLDER') ?>/?call=cities&countryId=<?php echo $item['Id'] ?>" target="_blank" class="q-result-query-item">[↗]</a></td>
                                        <td><?php echo htmlspecialchars($item['Id']) ?></td>
                                        <td><?php echo htmlspecialchars($item['Code']) ?></td>
                                        <td><?php echo htmlspecialchars($item['Name']) ?></td>
                                    </tr>
                                    <tr style="display: none;">
                                        <td colspan="12"><code></code></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                            </table>




                    <?php endif ?>
                <?php endif ?>

            </main>
        </div>
    </div>
</body>
<?php require __DIR__ . '/../common/footer.php' ?>