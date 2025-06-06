<?php $scripts['countries'] = 'hotels.js' ?>

<?php require __DIR__ . '/../common/head.php' ?>

<body>
    <?php require __DIR__ . '/../common/header.php' ?>
    <div class="container-fluid" style="padding-top: 20px;">
        <div class="row flex-xl-nowrap">
            <div style="padding-top: 20px;">
                <?php require __DIR__ . '/../common/menu.php' ?>
            </div>
            <main class="col-md-9 col-xl-8 py-md-3 pl-md-5 bd-content" role="main">
                <h3 class="bd-title" id="content">Hotels - List</h3>
                <div>
                    <pre class="">This service provides the list of all the hotels.</pre>
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
                                    </select>

                                </td>
                                <td></td>
                            </tr>

                            <tr>
                                <td>to[Handle]</td>
                                <td>
                                    <select name="to[Handle]">
                                    </select>
                                </td>
                                <td></td>
                            </tr>

                            <tr>
                                <td>to[ApiUrl]</td>
                                <td>
                                    <select name="to[ApiUrl]">
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
                                        <option value="api_getHotels">api_getHotels</option>
                                    </select>
                                </td>
                                <td> </td>
                            </tr>

                            <tr style="display: none">
                                <td>get-raw-data</td>
                                <td>
                                    <input type="hidden" name="get-to-requests" value="1">
                                </td>
                                <td></td>
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