$ = jQuery;

function submitBackupForm()
{
  let host_url = document.getElementById('edit-host-url').value;
  let response = document.querySelector('#drmanage-backupform #edit-response');
  response.innerHTML = "Backup is running. Please wait...\n\n";

  $.ajax({
    url: '/admin/drmanage/request_backup',
    type: 'POST',
    data: {
      'host_url': host_url
    },
    dataType: 'json',
    success: function(data) {
      for (msg of data.messages) {
        response.append(msg + "\n");
      }
      response.append("\nBackup is complete.\n");
    }
  });

  return false; // Prevent HTML form from submitting
}
