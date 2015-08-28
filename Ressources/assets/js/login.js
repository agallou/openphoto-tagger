$(document).ready(function() {
    function gotAssertion(assertion) {
        // got an assertion, now send it up to the server for verification
        if (assertion !== null) {
            $.ajax({
                type: 'POST',
                url: '/index.php/api/login',
                data: { assertion: assertion },
                success: function(res, status, xhr) {
                    if (res === null) {

                    } else {
                        window.location.reload(true);
                    }
                    //loggedOut();
                    //else loggedIn(res);
                },
                error: function(res, status, xhr) {
                    alert("login failure" + res);
                }
            });
        } else {
            //loggedOut();
        }
    }

    $('#browserid').click(function() {
        navigator.id.get(gotAssertion);
        return false;
    });
});
