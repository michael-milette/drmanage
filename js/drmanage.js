$ = jQuery;

function submitBackupForm()
{
  let app_name = document.getElementById('edit-app-name').value;
  let response = document.querySelector('#drmanage-backupform #edit-response');
  response.innerHTML = "Backup is starting. Please wait...\n";
  let job = '';
  
  $.ajax({
    url: '/admin/drmanage/request_backup',
    type: 'POST',
    data: {
      app_name: app_name
    },
    dataType: 'json',
    success: function(data) {
      if (data.status == 'ok') {
        job = data.job;
        let timer = setInterval(function() {
          $.ajax({
            url: '/admin/drmanage/query_job/' + job,
            type: 'POST',
            data: {
              app_name: app_name
            },
            success: function(data) {
              response.innerHTML = '';
              for (txt of data.messages) {
                response.append(txt + "\n");
              }
              if (data.status) {
                  clearInterval(timer);
                  response.append('Finished. Status=' + data.status);
              }
              response.scrollTop = response.scrollHeight - response.clientHeight;
            }
          });
        }, 1000);
      }
    }
  });

  return false; // Prevent HTML form from submitting
}

function submitRestoreForm()
{
  let app_name = document.getElementById('edit-app-name').value;
  let response = document.querySelector('#drmanage-restoreform #edit-response');
  let backup_file = document.querySelector('#edit-restore input[name="restore"]:checked').value;
  response.innerHTML = "Restore is starting. Please wait...\n";
  let job = '';
  
  $.ajax({
    url: '/admin/drmanage/request_restore',
    type: 'POST',
    data: {
      app_name: app_name,
      backup_file: backup_file
    },
    dataType: 'json',
    success: function(data) {
      if (data.status == 'ok') {
        job = data.job;
        let timer = setInterval(function() {
          $.ajax({
            url: '/admin/drmanage/query_job/' + job,
            type: 'POST',
            data: {
              app_name: app_name
            },
            success: function(data) {
              response.innerHTML = '';
              for (txt of data.messages) {
                response.append(txt + "\n");
              }
              if (data.status) {
                  clearInterval(timer);
                  response.append('Finished. Status=' + data.status);
              }
              response.scrollTop = response.scrollHeight - response.clientHeight;
            }
          });
        }, 1000);
      }
    }
  });

  return false; // Prevent HTML form from submitting
}

function updateRestoreOptions()
{
    let selector = document.getElementById('edit-app-name');
    let edit_restore = document.getElementById('edit-restore');
    let backup_type = document.getElementById('edit-backup-type');

    edit_restore.innerHTML = '';

    $.ajax({
      url: '/admin/drmanage/update_restore_options',
      type: 'POST',
      data: {
        'app_name': selector.value,
        'backup_type': backup_type.value,
      },
      dataType: 'json',
      success: function(data) {
        edit_restore.innerHTML = data.html.join("");
      }
    });

    return false; // Prevent HTML form from submitting
}
