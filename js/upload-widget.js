var $j = jQuery.noConflict();
var dropDisabled = true;
var completeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="3 3 16 16"><defs><linearGradient gradientUnits="userSpaceOnUse" y2="-2.623" x2="0" y1="986.67" id="0"><stop stop-color="#ffce3b"/><stop offset="1" stop-color="#ffd762"/></linearGradient><linearGradient y2="-2.623" x2="0" y1="986.67" gradientUnits="userSpaceOnUse"><stop stop-color="#ffce3b"/><stop offset="1" stop-color="#fef4ab"/></linearGradient></defs><g transform="matrix(1.99997 0 0 1.99997-10.994-2071.68)" fill="#da4453"><rect y="1037.36" x="7" height="8" width="8" fill="#32c671" rx="4"/><path d="m123.86 12.966l-11.08-11.08c-1.52-1.521-3.368-2.281-5.54-2.281-2.173 0-4.02.76-5.541 2.281l-53.45 53.53-23.953-24.04c-1.521-1.521-3.368-2.281-5.54-2.281-2.173 0-4.02.76-5.541 2.281l-11.08 11.08c-1.521 1.521-2.281 3.368-2.281 5.541 0 2.172.76 4.02 2.281 5.54l29.493 29.493 11.08 11.08c1.52 1.521 3.367 2.281 5.54 2.281 2.172 0 4.02-.761 5.54-2.281l11.08-11.08 58.986-58.986c1.52-1.521 2.281-3.368 2.281-5.541.0001-2.172-.761-4.02-2.281-5.54" fill="#fff" transform="matrix(.0436 0 0 .0436 8.177 1039.72)" stroke="none" stroke-width="9.512"/></g></svg>';
var uploadIconSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="50" height="43" viewBox="0 0 50 43"><path d="M48.4 26.5c-.9 0-1.7.7-1.7 1.7v11.6h-43.3v-11.6c0-.9-.7-1.7-1.7-1.7s-1.7.7-1.7 1.7v13.2c0 .9.7 1.7 1.7 1.7h46.7c.9 0 1.7-.7 1.7-1.7v-13.2c0-1-.7-1.7-1.7-1.7zm-24.5 6.1c.3.3.8.5 1.2.5.4 0 .9-.2 1.2-.5l10-11.6c.7-.7.7-1.7 0-2.4s-1.7-.7-2.4 0l-7.1 8.3v-25.3c0-.9-.7-1.7-1.7-1.7s-1.7.7-1.7 1.7v25.3l-7.1-8.3c-.7-.7-1.7-.7-2.4 0s-.7 1.7 0 2.4l10 11.6z"></path></svg>';
var enterTarget = null;

$j(document).ready(function() {

    checkProcessingStatus();

    // preventing page from redirecting
    $j("html").on("dragenter", function(e) {
        e.preventDefault();
        e.stopPropagation();
        enterTarget = e.target;
        if (!dropDisabled) {
            $j("#oalm_drop_text").html(uploadIconSVG + "<br>Drag here to upload");
        }
    });

    $j("html").on("drop", function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    // Drag leave
    $j('html').on('dragleave', function (e) {
        e.stopPropagation();
        e.preventDefault();
        if (!dropDisabled) {
            if (enterTarget == e.target) {
                resetForm();
            }
        }
    });

    // Drag enter
    $j('.upload-area').on('dragenter', function (e) {
        e.stopPropagation();
        e.preventDefault();
        if (!dropDisabled) {
            $j("#oalm_drop_text").html(uploadIconSVG + "<br>Drop now to upload");
        }
    });

    // Drag over
    $j('.upload-area').on('dragover', function (e) {
        e.stopPropagation();
        e.preventDefault();
        if (!dropDisabled) {
            $j("#oalm_drop_text").html(uploadIconSVG + "<br>Drop now to upload");
        }
    });

    // Drop
    $j('.upload-area').on('drop', function (e) {
        e.stopPropagation();
        e.preventDefault();

        if (!dropDisabled) {
            $j("#oalm_drop_text").text("Uploading");
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 1) {
                $j("#oalm_drop_text").html('<span style="color: red;">Too many files! Only choose one file to upload!</span>');
                setTimeout(resetForm, 2000);
                return;
            }
            file = files[0];
            ext = file.name.substr(file.name.lastIndexOf('.'));
            filetypes = $j("#oalm_file").attr("accept").split(",");
            if (filetypes.indexOf(ext) < 0) {
                $j("#oalm_drop_text").html('<span style="color: red;">Invalid filetype! Must be one of<br>' + filetypes.join(", ") + '</span>');
                setTimeout(resetForm, 4000);
                return;
            }

            oalm.oalm_file = file;
            dropDisabled = true;
            uploadData(file);
        }
    });

    // Open file selector on div click
    $j("#uploadfile").click(function(){
        if (!dropDisabled) {
            $j("#oalm_file").click();
        }
    });

    // file selected
    $j("#oalm_file").change(function(){
        if (!dropDisabled) {
            var file = $j('#oalm_file')[0].files[0];
            oalm.oalm_file = file;
            uploadData(file);
        }
    });

    // wire up reset button
    $j("#oalm_reset_button").click(function(){
		$j.ajax({
			type: "GET",
			url: oalm.wp_ajax_url + "?action=oalm_ack_complete",
		});
        $j("#oalm_drop_text").html('<img src="' + oalm.wp_site_url + '/wp-includes/images/wpspin-2x.gif"><br>Resetting...');
        $j("#oalm_drop_text").show();
        $j("#oalm_drop_progressBar").hide();
        $j("#oalm_drop_processBar").hide();
        $j("#oalm_drop_status").hide();
        $j("#oalm_drop_filename").hide();
        setTimeout(checkProcessingStatus, 1000);
        return false;
    });
});

function resetForm() {
    $j("#uploadfile #oalm_drop_text").show();
    $j("#oalm_drop_progressBar").hide();
    $j("#oalm_drop_processBar").hide();
    $j("#oalm_drop_status").hide();
    $j("#oalm_drop_filename").hide();
    $j("#oalm_drop_text").html(uploadIconSVG + '<br>Drag and Drop file here Or <b>Click to select file</b>');
    dropDisabled = false;
}

function checkProcessingStatus(){
    var ajax = new XMLHttpRequest();
    var formdata = new FormData();
    formdata.append('action','oalm_import_status');
    ajax.addEventListener("load", function(event){
        // not really percent, it returns 0..1000 instead of 0..100
        response = JSON.parse(event.target.responseText);
        console.log("checkfile response: " + response);
        if (response.status == 'ready') {
            resetForm();
        } else if (response.status == 'error') {
            // transcoding returned an error
            errortext = response.errortext;
            $j('#oalm_drop_text').html('<img alt="" src="' + oalm.wp_site_url + '/wp-includes/images/smilies/frownie.png">');
            $j('#oalm_drop_status').html('Processing the file returned an error:<br><span style="color:red;">'+errortext+'</span><br>Please fix any issues with the file and upload it again.<br>Contact ' + oalm.wp_admin_email + ' if you need help.');
            $j('#oalm_drop_status').show();
            $j('#oalm_drop_text').show();
            $j('#oalm_drop_progressBar').hide();
            setTimeout(resetForm, 5000);
            alert("Processing your file returned an error:\n" + errortext + "\nPlease fix any issues with the file and upload it again.\nContact " + oalm.wp_admin_email + " if you need help.");
        } else if (response.status == 'processing') {
            percent = response.progress;
            $j("#oalm_drop_progressBar").val(Math.round(percent));
            $j("#oalm_drop_status").html("Your upload is being processed.<br>Leaving this page will not interrupt processing.<br>You can come back to this page at any time to check the status.<br>You must wait for it to finish before you can try again.<br><br>"+Math.round(percent/10)+"% processed");
            $j('#oalm_drop_status').show();
            $j('#oalm_drop_progressBar').show();
            $j('#oalm_drop_text').hide();
            setTimeout(checkProcessingStatus, 5000);
        } else if (response.status == 'waiting') {
            $j("#oalm_drop_progressBar").val(0);
            $j("#oalm_drop_status").html("Your upload is being processed.<br>Leaving this page will not interrupt processing.<br>You can come back to this page at any time to check the status.<br>You must wait for it to finish before you can try again.<br><br>Waiting for cron job...");
            $j('#oalm_drop_status').show();
            $j('#oalm_drop_progressBar').show();
            $j('#oalm_drop_text').hide();
            setTimeout(checkProcessingStatus, 5000);
        } else if (response.status == 'completed') {
            percent = 1000;
            $j("#oalm_drop_progressBar").val(Math.round(percent));
            $j("#oalm_drop_status").html("Your upload is being processed.<br>Leaving this page will not interrupt processing.<br>You can come back to this page at any time to check the status.<br>You must wait for it to finish before you can try again.<br><br>"+Math.round(percent/10)+"% processed");
            $j('#oalm_drop_status').show();
            $j('#oalm_drop_progressBar').show();
            $j('#oalm_drop_text').hide();
            $j('#oalm_import_output').html(response.output);
            $j.ajax({
                type: "GET",
                url: oalm.wp_ajax_url + "?action=oalm_ack_complete",
            });
            setTimeout(checkProcessingStatus, 500);
        } else {
            $j('#oalm_drop_text').html('<img alt="" src="' + oalm.wp_site_url + '/wp-includes/images/smilies/frownie.png">');
            if (response.status) {
                $j('#oalm_drop_status').html("Got a unknown response status from the server:<br>"+response.status+"<br>Please try again in a few minutes, and contact<br>" + oalm.wp_admin_email + " if this message persists.");
            } else {
                $j('#oalm_drop_status').html("Got a unknown status from the server:<br>"+event.target.responseText+"<br>Please try again in a few minutes, and contact<br>" + oalm.wp_admin_email + " if this message persists.");
            }
            $j('#oalm_drop_status').show();
            $j('#oalm_drop_progressBar').hide();
            setTimeout(checkProcessingStatus, 5000);
        }
    }, false);
    ajax.addEventListener("timeout", function(event){
        setTimeout(checkProcessingStatus, 10);
    });
    ajax.addEventListener("error", function(event){
        $j('#oalm_drop_text').html('<img alt="" src="' + oalm.wp_site_url + '/wp-includes/images/smilies/frownie.png">');
        $j("#oalm_drop_status").html("Failed to check the current status.<br>Please try again in a few minutes, and contact<br>" + oalm.wp_admin_email + " if this message persists.");
        $j('#oalm_drop_status').show();
        setTimeout(checkProcessingStatus, 5000);
    }, false);
    ajax.addEventListener("abort", function(event){
        //$j('#oalm_drop_text').html('<img alt="" src="' + oalm.wp_site_url + '/wp-includes/images/smilies/frownie.png">');
        //$j("#oalm_drop_status").html("Aborted.");
        //$j('#oalm_drop_status').show();
    }, false);
    ajax.open("POST", oalm.wp_ajax_url);
    ajax.timeout = 5000; // only wait 5 seconds
    ajax.send(formdata);
}

// Sending AJAX request and upload file
function uploadData(file){
    $j("#uploadfile #oalm_drop_text").hide();
    $j("#oalm_drop_progressBar").show();
    $j("#oalm_drop_status").show();
    $j("#oalm_drop_filename").show();
    $j("#oalm_drop_filename").html('Uploading "' + file.name + '" (' + convertSize(file.size) + ')');
    $j('.upload-area').css("cursor","wait");
    formdata = new FormData();
    formdata.append('action','oalm_process_import_upload');
    formdata.append('oalm_file',file);
    var started_at = new Date();
    var ajax = new XMLHttpRequest();
    var last_checked = 0;
    var seconds_remaining_text = '(calculating time remaining)';
    ajax.upload.addEventListener("progress", function(event){
        var seconds_elapsed =  Math.floor(( new Date().getTime() - started_at.getTime() )/1000);
        var bytes_per_second = seconds_elapsed ? event.loaded / seconds_elapsed : 0 ;
        var remaining_bytes =  event.total - event.loaded;
        if (seconds_elapsed > last_checked) {
            // don't update this more than once per second
            last_checked = seconds_elapsed;
            seconds_remaining_text = humanizeDuration(Math.ceil(remaining_bytes / bytes_per_second)) + " remaining";
        }
        var percent = (event.loaded / event.total) * 100;
        if (percent == 100) { seconds_remaining_text = ''; }
        $j("#oalm_drop_progressBar").val(Math.round(percent * 10));
        $j("#oalm_drop_status").html(Math.round(percent)+"% uploaded... "+seconds_remaining_text);
        //$j("#oalm_drop_status").html(Math.round(percent)+"% uploaded... please wait");
    }, false);
    ajax.addEventListener("load", function(event){
        response = JSON.parse(event.target.responseText);
        responsetext = '';
        if (response.status == 'error') {
            $j('#oalm_drop_text').html('<img alt="" src="' + oalm.wp_site_url + '/wp-includes/images/smilies/frownie.png">');
            responsetext = response.errortext;
        } else {
            $j('#oalm_drop_text').html(completeSVG + "<br>Uploaded file will begin processing momentarily...");
            setTimeout(checkProcessingStatus, 500);
        }
        $j("#oalm_drop_status").html(responsetext);
        $j("#oalm_drop_progressBar").hide();
        $j("#oalm_drop_filename").hide();
        $j('#oalm_drop_text').show();
        $j('.upload-area').css("cursor","auto");
    }, false);
    ajax.addEventListener("error", function(event){
        $j("#oalm_drop_status").html("Upload Failed");
    }, false);
    ajax.addEventListener("abort", function(event){
        $j("#oalm_drop_status").html("Upload Aborted");
    }, false);
    ajax.open("POST", oalm.wp_ajax_url);
    ajax.send(formdata);
}

// Bytes conversion
function convertSize(size) {
    var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    if (size == 0) return '0 Byte';
    var i = parseInt(Math.floor(Math.log(size) / Math.log(1024)));
    return Math.round(size / Math.pow(1024, i), 2) + ' ' + sizes[i];
}

function humanizeDuration(seconds) {
    var s = "";

    if (seconds == 0) { return "0s"; }
    var days = Math.floor( ( seconds / 3600 ) / 24 );
    if ( days >= 1 ) {
        s += days.toString() + "d "
        seconds -= days * 24 * 3600;
    }

    var hours = Math.floor( seconds / 3600 );
    if ( hours >= 1 ) {
        s += hours.toString() + "h ";
        seconds -= hours * 3600;
    }

    var minutes = Math.floor( seconds / 60 );
    if ( minutes >= 1 ) {
        s += minutes.toString() + "m ";
        seconds -= minutes * 60;
    }

    if ( seconds >= 1 ) {
        s += Math.floor( seconds ).toString() + "s";
    }

    return s;
}
