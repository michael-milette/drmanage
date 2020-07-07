console.log("ok drmanage here!");

$ = jQuery;

function doit()
{
    let host_url = document.getElementById('edit-host-url').value;
    let somevalue = document.getElementById('edit-somevalue').value;

    console.log('POST to ' + host_url + '/test.php');

    $.ajax({
        url: '/admin/drmanage/sendreq',
        type: 'POST',
        data: {'somevalue': somevalue},
        dataType: 'json',
        success: function(data) {
            document.querySelector('#drmanage-backupform #edit-response').innerHTML = JSON.stringify(data);
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
