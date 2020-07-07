console.log("ok drmanage here!");

$ = jQuery;

function doit()
{
    let host_url = document.getElementById('edit-host-url').value;

    console.log('POST to ' + host_url + '/test.php');

    $.ajax({
        url: host_url + '/test.php',
        type: 'POST',
        data: {'somevalue': 'This is a test'},
        dataType: 'json',
        success: function(data) {
            document.querySelector('#drmanage-backupform #edit-result').innerHTML = JSON.stringify(data);
            console.log('got data ', data)
        }
      });

    return false;
}

document.addEventListener('DOMContentLoaded', function () {
//    let selectors = document.querySelectorAll('#drmanage-backupform .js-form-submit');
//    for (var i = 0; i < selectors.length; i++) {
//        selectors[i].addEventListener('click', function() {
//        });
//    }
});
