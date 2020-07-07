console.log("ok drmanage here.");

document.addEventListener('DOMContentLoaded', function () {
    let selectors = document.querySelectorAll('.product-select');
    for (var i = 0; i < selectors.length; i++) {
        selectors[i].addEventListener('click', function() {
            if (this.checked) {
                axios.get('/api/select-product?id=' + this.value)
                    .then(response => {
                        
                    });
            }
            else {
                axios.get('/api/unselect-product?id=' + this.value)
                .then(response => {
                    
                });
            }
        });
    }
});
