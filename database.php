  <html>
    <head>
        <title>304</title>
    </head>
    <body>
        <h1>Customer Menu</h2>

        <hr />

        <h2>View the number of available vehicles</h2>
        <form method="GET" action="304.php"> 
            Car type: <input list="CarType" name='CarType'>
                    <datalist id="CarType">
                        <option value="Economy">
                        <option value="Compact">
                        <option value="Mid-size">
                        <option value="Standard">
                        <option value="Full-size">
                        <option value="SUV">
                        <option value="Truck">
                    </datalist>
            Location: <input type="text" name="Location" value="vancouver"><br>
            Time interval: <input type="text" name="TimeIntervalStartDate" size=10 value="01-JAN-20">
                            <input type="text" name="TimeIntervalStartTime" size=5 value="00:00">
            -               <input type="text" name="TimeIntervalEndDate" size=10 value="31-DEC-20">
                            <input type="text" name="TimeIntervalEndTime" size=5 value="23:59">
            <input type="hidden" id="ViewVehiclesRequest" name="ViewVehiclesRequest"><br>
            <input type="submit" name="ViewVehicles"></p>
        </form>

        <?php
        $success = True; //keep track of errors so it redirects the page only if there are no errors
        $db_conn = NULL; // edit the login credentials in connectToDB()
        $show_debug_alert_messages = False; // set to True if you want alerts to show you which methods are being triggered (see how it is used in debugAlertMessage())

        function debugAlertMessage($message) {
            global $show_debug_alert_messages;

            if ($show_debug_alert_messages) {
                echo "<script type='text/javascript'>alert('" . $message . "');</script>";
            }
        }

        function executePlainSQL($cmdstr) {
            //echo "<br>running ".$cmdstr."<br>";
            global $db_conn, $success;

            $statement = OCIParse($db_conn, $cmdstr);

            if (!$statement) {
                echo "<br>Cannot parse the following command: " . $cmdstr . "<br>";
                $e = OCI_Error($db_conn);
                echo htmlentities($e['message']);
                $success = False;
            }

            $r = OCIExecute($statement, OCI_DEFAULT);
            if (!$r) {
                echo "<br>Cannot execute the following command: " . $cmdstr . "<br>";
                $e = oci_error($statement);
                echo htmlentities($e['message']);
                $success = False;
            }

			return $statement;
		}

        function executeBoundSQL($cmdstr, $list) {

            global $db_conn, $success;
            $success = True;
			$statement = OCIParse($db_conn, $cmdstr);

            if (!$statement) {
                echo "<br>Cannot parse the following command: " . $cmdstr . "<br>";
                $e = OCI_Error($db_conn);
                echo htmlentities($e['message']);
                $success = False;
            }

            foreach ($list as $tuple) {
                foreach ($tuple as $bind => $val) {
                    //echo $val;
                    //echo "<br>".$bind."<br>";
                    OCIBindByName($statement, $bind, $val);
                    unset ($val);
				}

                $r = OCIExecute($statement, OCI_DEFAULT);
                if (!$r) {
                    echo "<br>Cannot execute the following command: " . $cmdstr . "<br>";
                    $e = OCI_Error($statement);
                    echo htmlentities($e['message']);
                    echo "<br>";
                    $success = False;
                }
            }
        }

        function connectToDB() {
            global $db_conn;

            // Your username is ora_(CWL_ID) and the password is a(student number). For example,
			// ora_platypus is the username and a12345678 is the password.
            $db_conn = OCILogon("login_name", "login_password", "dbhost.students.cs.ubc.ca:1522/stu");

            if ($db_conn) {
                debugAlertMessage("Database is Connected");
                return true;
            } else {
                debugAlertMessage("Cannot connect to Database");
                $e = OCI_Error(); // For OCILogon errors pass no handle
                echo htmlentities($e['message']);
                return false;
            }
        }

        function disconnectFromDB() {
            global $db_conn;

            debugAlertMessage("Disconnect from Database");
            OCILogoff($db_conn);
        }

        function getSDate() {
            return strtotime($_GET["TimeIntervalStartDate"]);
        }

        function getEDate() {
            return strtotime($_GET["TimeIntervalEndDate"]);
        }

        function handleViewVehiclesRequest() {
            global $db_conn;
            if (getSDate()>getEDate()) {
                //
                $c = false;
            } else if (getSDate()<time()) {
                //
                $c = false;
            } else if (!$_GET["CarType"]) {
                $sql = viewSQL1();
                $c = true;
            } else {
                $sql = viewSQL2();
                $c = true;
            }

            if(!$_GET["CarType"]) {
                $_GET["CarType"] = "all types of";
            }
            if(!$_GET["Location"]) {
                $_GET["Location"] = "all locations";
            }

            echo "The number of " . $_GET["CarType"] . " cars in " . $_GET["Location"] .
            " during " . $_GET["TimeIntervalStartDate"] . " " . $_GET["TimeIntervalStartTime"] .
            " to " . $_GET["TimeIntervalEndDate"] . " " . $_GET["TimeIntervalEndTime"] . " is: " ;

            if($c) {
                $result = executePlainSQL($sql);

            $table = '';
            $table .= '<table border="1">';
            $count = 0;
            while ($row = oci_fetch_array($result, OCI_RETURN_NULLS+OCI_ASSOC)) {
                $table .= '<tr>';
                foreach ($row as $item) {
                    $table .= '<td>'.($item !== null ? htmlentities($item, ENT_QUOTES) : '&nbsp').'</td>';
                }
                $table .= '</tr>';
                $count++;
            }
            $table .= '</table>';

            echo "<input type='button' value=$count onclick='clickBut();' />";
            echo "<script> function clickBut(){document.getElementById('Tony').innerHTML = '$table';} </script>";
            } else {
                echo "0";
            }
        }

        function viewSQL1() {
            return "SELECT * FROM
                    (SELECT *
                    FROM vehicle v2
                    WHERE v2.city = '" . $_GET["Location"] . "'
                    MINUS
                    SELECT v1.*
                    FROM vehicle v1, rent
                    WHERE v1.city = '" . $_GET["Location"] . "' AND rent.vid = v1.vid
                        AND (('" . $_GET["TimeIntervalStartDate"] . "'<rent.ToDate AND '" . $_GET["TimeIntervalStartDate"] . "'>rent.fromDate)
                            OR ('" . $_GET["TimeIntervalEndDate"] . "'<rent.ToDate AND '" . $_GET["TimeIntervalEndDate"] . "'>rent.fromDate))
                    )
                    ORDER BY vtname";
        }

        function viewSQL2() {
            return "SELECT * FROM
                    (SELECT *
                    FROM vehicle v2
                    WHERE v2.city = '" . $_GET["Location"] . "' AND v2.vtname = '" . $_GET["CarType"] . "'
                    MINUS
                    SELECT v1.*
                    FROM vehicle v1, rent
                    WHERE v1.city = '" . $_GET["Location"] . "' AND v1.vtname = '" . $_GET["CarType"] . "' AND rent.vid = v1.vid
                    AND (('" . $_GET["TimeIntervalStartDate"] . "'<rent.ToDate AND '" . $_GET["TimeIntervalStartDate"] . "'>rent.fromDate)
                            OR ('" . $_GET["TimeIntervalEndDate"] . "'<rent.ToDate AND '" . $_GET["TimeIntervalEndDate"] . "'>rent.fromDate))
                    )
                    ORDER BY vtname";
        }


        function handleGETRequest() {
            if (connectToDB()) {
                if (array_key_exists('ViewVehicles', $_GET)) {
                    handleViewVehiclesRequest();
                }

                disconnectFromDB();
            }
        }

		if (isset($_GET['ViewVehiclesRequest'])) {
            handleGETRequest();
        }
		?>

        <label id = "Tony"></label>

        <h2>New customer</h2>
        <form method="POST" action="304.php">
            <input type="hidden" id="addNewCustomer" name="addNewCustomer">
            Cell phone number: <input type="text" name="cellPhone" value="6041234567" size=12>  <br /><br />
            Name: <input type="text" name="name" value="FirstNmae LastName"> <br /><br />
            Address: <input type="text" name="address" value="2366 Main Mall"> <br /><br />
            Driver License: <input type="text" name="dlicense" value="1234560"> <br /><br />
            <input type="submit" value="Insert" name="insertSubmit"></p>
        </form>

        <?php
        function handleAddNewCustomer(){
            global $db_conn, $success;

            $tuple = array (
                ":bind1" => $_POST['cellPhone'],
                ":bind2" => $_POST['name'],
                ":bind3" => $_POST['address'],
                ":bind4" => $_POST['dlicense']
            );

            $alltuples = array (
                $tuple
            );

            executeBoundSQL("insert into Customers values (:bind1, :bind2, :bind3, :bind4)", $alltuples);
            OCICommit($db_conn);
            if ($success == false) {
                echo "error!";
            } else {
                echo "added successfully!";
            }
        }

        function handlePOSTRequest() {
            if (connectToDB()) {
                if (array_key_exists('addNewCustomer', $_POST)) {
                    handleAddNewCustomer();
                }
                disconnectFromDB();
            }
        }

        if (isset($_POST['addNewCustomer'])) {
            handlePOSTRequest();
        }
        ?>

        <h2>Make reservations</h2>
        <form method="POST" action="304.php">
            Car type: <input list="CarType" name='CarType'>
                    <datalist id="CarType">
                        <option value="Economy">
                        <option value="Compact">
                        <option value="Mid-size">
                        <option value="Standard">
                        <option value="Full-size">
                        <option value="SUV">
                        <option value="Truck">
                    </datalist>
            Location: <input type="text" name="Location" value="vancouver"><br>
            Time interval: <input type="text" name="TimeIntervalStartDate" size=10 value="01-JAN-20">
                            <input type="text" name="TimeIntervalStartTime" size=5 value="00:00">
            -               <input type="text" name="TimeIntervalEndDate" size=10 value="31-DEC-20">
                            <input type="text" name="TimeIntervalEndTime" size=5 value="23:59"><br /><br />
            Cell phone number: <input type="text" name="phone" value="6041234567" size=12>  <br />
            Name: <input type="text" name="Rname" value="FirstNmae LastName"> <br />
            <input type="hidden" id="MakeReservationRequest" name="MakeReservationRequest"><br>
            <input type="submit" name="view" value="View vehicles">
            <input type="submit" name="make" value="Make reservations"></p>
        </form>

        <?php
            function handleViewReservation() {
                if (!$_POST["CarType"]) {
                    $sql = makeSQL1();
                } else {
                    $sql = makeSQL2();
                }
                $result = executePlainSQL($sql);

                $table = '';
                $table .= '<table border="1">';
                $count = 0;
                while ($row = oci_fetch_array($result, OCI_RETURN_NULLS+OCI_ASSOC)) {
                    $table .= '<tr>';
                    foreach ($row as $item) {
                        $table .= '<td>'.($item !== null ? htmlentities($item, ENT_QUOTES) : '&nbsp').'</td>';
                    }
                    $table .= '</tr>';
                    $count++;
                }
                $table .= '</table>';
                if ($count==0) {
                    echo "<script> alert('Sorry, desired vehicle is not available') </script>";
                } else {
                    echo $table;
                    echo "additional equipment available for a truck: ";
                    echo "<input type='button' value='lift gate' onclick='lift();' />";
                    echo "<input type='button' value='car-towing equipmen' onclick='tow();' />";
                    echo "<br>";
                    echo "additional equipment available for other cars: ";
                    echo "<input type='button' value='ski rack' onclick='ski();' />";
                    echo "<input type='button' value='child safety seats' onclick='child();' />";
                    echo "<br>";
                    echo "<input type='button' value='reset' onclick='reset();' />";
                    echo "<script>
                        var sum = 50;
                        function eco(){sum = 20; document.getElementById('cost').innerHTML = sum;}
                        function ski(){sum += 8; document.getElementById('cost').innerHTML = sum;}
                        function child(){sum += 5; document.getElementById('cost').innerHTML = sum;}
                        function lift(){sum += 13; document.getElementById('cost').innerHTML = sum;}
                        function tow(){sum += 15; document.getElementById('cost').innerHTML = sum;}
                        function reset(){sum = 50; document.getElementById('cost').innerHTML = sum;}
                        </script>";
                }
                return $count;
            }

            function makeSQL1() {
                return "SELECT * FROM
                        (SELECT *
                        FROM vehicle v2
                        WHERE v2.city = '" . $_POST["Location"] . "'
                        MINUS
                        SELECT v1.*
                        FROM vehicle v1, rent
                        WHERE v1.city = '" . $_POST["Location"] . "' AND rent.vid = v1.vid
                            AND (('" . $_POST["TimeIntervalStartDate"] . "'<rent.ToDate AND '" . $_POST["TimeIntervalStartDate"] . "'>rent.fromDate)
                                OR ('" . $_POST["TimeIntervalEndDate"] . "'<rent.ToDate AND '" . $_POST["TimeIntervalEndDate"] . "'>rent.fromDate))
                        )
                        ORDER BY vtname";
            }

            function makeSQL2() {
                return "SELECT * FROM
                        (SELECT *
                        FROM vehicle v2
                        WHERE v2.city = '" . $_POST["Location"] . "' AND v2.vtname = '" . $_POST["CarType"] . "'
                        MINUS
                        SELECT v1.*
                        FROM vehicle v1, rent
                        WHERE v1.city = '" . $_POST["Location"] . "' AND v1.vtname = '" . $_POST["CarType"] . "' AND rent.vid = v1.vid
                        AND (('" . $_POST["TimeIntervalStartDate"] . "'<rent.ToDate AND '" . $_POST["TimeIntervalStartDate"] . "'>rent.fromDate)
                                OR ('" . $_POST["TimeIntervalEndDate"] . "'<rent.ToDate AND '" . $_POST["TimeIntervalEndDate"] . "'>rent.fromDate))
                        )
                        ORDER BY vtname";
            }

            function handleMakeReservation() {
                global $db_conn, $success;

                if (!$_POST['CarType'] || !$_POST['Location'] || !$_POST['TimeIntervalStartDate'] || !$_POST["TimeIntervalStartTime"]
                        || !$_POST["TimeIntervalEndDate"] || !$_POST["TimeIntervalEndTime"] || !$_POST["phone"] || !$_POST["Rname"]) {
                    echo "<script> alert('Completing a reservation without all the required information!') </script>";
                } else {


                    $sql = "SELECT Count(*) FROM
                        (SELECT *
                        FROM vehicle v2
                        WHERE v2.city = '" . $_POST["Location"] . "' AND v2.vtname = '" . $_POST["CarType"] . "'
                        MINUS
                        SELECT v1.*
                        FROM vehicle v1, rent
                        WHERE v1.city = '" . $_POST["Location"] . "' AND v1.vtname = '" . $_POST["CarType"] . "' AND rent.vid = v1.vid
                        AND (('" . $_POST["TimeIntervalStartDate"] . "'<rent.ToDate AND '" . $_POST["TimeIntervalStartDate"] . "'>rent.fromDate)
                                OR ('" . $_POST["TimeIntervalEndDate"] . "'<rent.ToDate AND '" . $_POST["TimeIntervalEndDate"] . "'>rent.fromDate))
                        )";


                $result = executePlainSQL($sql);

                if (($row = oci_fetch_row($result)) != false) {
                    if ($row[0] == 0) {
                        echo "<script> alert('Sorry, desired vehicle is not available') </script>";
                    } else {
                        $tuple = array (
                            ":bind1" => $_POST['TimeIntervalStartDate'],
                            ":bind2" => $_POST['TimeIntervalStartTime'],
                            ":bind3" => $_POST['TimeIntervalEndDate'],
                            ":bind4" => $_POST['TimeIntervalEndTime']
                        );
                        $alltuples = array (
                            $tuple
                        );



			            $statement = OCIParse($db_conn, "insert into TimePeriod values (:bind1, :bind2, :bind3, :bind4)");

                        foreach ($list as $tuple) {
                            foreach ($tuple as $bind => $val) {
                                OCIBindByName($statement, $bind, $val);
                                unset ($val);

                            $r = OCIExecute($statement, OCI_DEFAULT);
                        }

                        OCICommit($db_conn);

                        $confNum = generateConfNum();

                        $tuple = array (
                            ":bind1" => $confNum,
                            ":bind2" => $_POST['CarType'],
                            ":bind3" => $_POST['phone'],
                            ":bind4" => $_POST['TimeIntervalStartDate'],
                            ":bind5" => $_POST['TimeIntervalStartTime'],
                            ":bind6" => $_POST['TimeIntervalEndDate'],
                            ":bind7" => $_POST['TimeIntervalEndTime']
                        );

                        $alltuples = array (
                            $tuple
                        );

                        executeBoundSQL("insert into Reservation values (:bind1, :bind2, :bind3, :bind4, :bind5, :bind6, :bind7)", $alltuples);
                        OCICommit($db_conn);

                        if ($success == True) {
                            echo "Your confirmation number for the reservation is: ".$confNum;
                            echo "<br>";
                            echo "Car type: ".$_POST['CarType']."; Location: ".$_POST['Location'].
                                "; Time interval: ".$_POST['TimeIntervalStartDate']." ".$_POST['TimeIntervalStartTime']." - ".
                                $_POST['TimeIntervalEndDate']." ".$_POST['TimeIntervalEndTime'];
                            echo "<br>";
                            echo "Name: ".$_POST['Rname']."; Cell phone number: ".$_POST['phone'];
                            echo "<br>";
                            echo "Reservation made!";
                        } else {
                            echo "Error!";
                        }
                    }
                }
            }
            }

            function generateConfNum() {
                $sql = "SELECT MAX(confNo)
                        FROM Reservation";
                $result = executePlainSQL($sql);
                if (($row = oci_fetch_row($result)) != false) {
                    return $row[0]+1;
                }
            }

            if (isset($_POST['MakeReservationRequest'])) {
                if (connectToDB()) {
                    if (array_key_exists('MakeReservationRequest', $_POST)) {
                        if ($_REQUEST['view']) {
                            handleViewReservation();
                        } else if ($_REQUEST['make']) {
                            handleMakeReservation();
                        }
                    }
                    disconnectFromDB();
                }
            }
        ?>

    <br>
    The estimatedcost is <label id = "cost">50</label>

	</body>
</html>
