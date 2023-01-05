M.mod_svasuform = {};
M.mod_svasuform.init = function(Y) {
    var svasuform = Y.one('#svasuviewform');
    var cwidth = svasuplayerdata.cwidth;
    var cheight = svasuplayerdata.cheight;
    var poptions = svasuplayerdata.popupoptions;
    var launch = svasuplayerdata.launch;
    var currentorg = svasuplayerdata.currentorg;
    var sco = svasuplayerdata.sco;
    var svasu = svasuplayerdata.svasu;
    var launch_url = M.cfg.wwwroot + "/mod/svasu/player.php?a=" + svasu + "&currentorg=" + currentorg + "&scoid=" + sco + "&sesskey=" + M.cfg.sesskey + "&display=popup";
    var course_url = svasuplayerdata.courseurl;
    var winobj = null;

    poptions = poptions + ',resizable=yes'; // Added for IE (MDL-32506).

    if ((cwidth == 100) && (cheight == 100)) {
        poptions = poptions + ',width=' + screen.availWidth + ',height=' + screen.availHeight + ',left=0,top=0';
    } else {
        if (cwidth <= 100) {
            cwidth = Math.round(screen.availWidth * cwidth / 100);
        }
        if (cheight <= 100) {
            cheight = Math.round(screen.availHeight * cheight / 100);
        }
        poptions = poptions + ',width=' + cwidth + ',height=' + cheight;
    }

    // Hide the form and toc if it exists - we don't want to allow multiple submissions when a window is open.
    var svasuload = function () {
        if (svasuform) {
            svasuform.hide();
        }

        var svasutoc = Y.one('#toc');
        if (svasutoc) {
            svasutoc.hide();
        }
        // Hide the intro and display a message to the user if the window is closed.
        var svasuintro = Y.one('#intro');
        svasuintro.setHTML('<a href="' + course_url + '">' + M.util.get_string('popuplaunched', 'svasu') + '</a>');
    }

    // When pop-up is closed return to course homepage.
    var svasuunload = function () {
        // Onunload is called multiple times in the SVASU window - we only want to handle when it is actually closed.
        setTimeout(function() {
            if (winobj.closed) {
                window.location = course_url;
            }
        }, 800)
    }

    var svasuredirect = function (winobj) {
        Y.on('load', svasuload, winobj);
        Y.on('unload', svasuunload, winobj);
        // Check to make sure pop-up has been launched - if not display a warning,
        // this shouldn't happen as the pop-up here is launched on user action but good to make sure.
        setTimeout(function() {
            if (!winobj) {
                var svasuintro = Y.one('#intro');
                svasuintro.setHTML(M.util.get_string('popupsblocked', 'svasu'));
            }}, 800);
    }

    // Set mode and newattempt correctly.
    var setlaunchoptions = function() {
        var mode = Y.one('#svasuviewform input[name=mode]:checked');
        if (mode) {
            var modevalue = mode.get('value');
            launch_url += '&mode=' + (modevalue ? modevalue : 'normal');
        } else {
            launch_url += '&mode=normal';
        }

        var newattempt = Y.one('#svasuviewform #a');
        launch_url += (newattempt && newattempt.get('checked') ? '&newattempt=on' : '');
    }

    if (launch == true) {
        setlaunchoptions();
        winobj = window.open(launch_url,'Popup', poptions);
        this.target = 'Popup';
        svasuredirect(winobj);
        winobj.opener = null;
    }
    // Listen for view form submit and generate popup on user interaction.
    if (svasuform) {
        Y.on('submit', function(e) {
            setlaunchoptions();
            winobj = window.open(launch_url, 'Popup', poptions);
            this.target = 'Popup';
            svasuredirect(winobj);
            winobj.opener = null;
            e.preventDefault();
        }, svasuform);
    }
}
