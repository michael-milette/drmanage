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

function submitRestoreForm()
{
  let host_url = document.getElementById('edit-host-url').value;
  let response = document.querySelector('#drmanage-restoreform #edit-response');
  let backup_file = document.querySelector('#edit-restore input[name="restore"]:checked').value;
  response.innerHTML = "Restore is in progress. Please wait...\n\n";

  $.ajax({
    url: '/admin/drmanage/request_restore',
    type: 'POST',
    data: {
      'host_url': host_url,
      'backup_file': backup_file
    },
    dataType: 'json',
    success: function(data) {
      for (msg of data.messages) {
        response.append(msg + "\n");
      }
      response.append("\nRestore is complete.\n");
    }
  });

  return false; // Prevent HTML form from submitting
}

function updateRestoreOptions(selector)
{
    console.log('selector changed to ' + selector.value);
    let edit_restore = document.getElementById('edit-restore');
    edit_restore.innerHTML = '';

    $.ajax({
      url: '/admin/drmanage/update_restore_options',
      type: 'POST',
      data: {
        'host_url': selector.value,
      },
      dataType: 'json',
      success: function(data) {
        edit_restore.innerHTML = data.html;
      }
    });

    return false; // Prevent HTML form from submitting
}
