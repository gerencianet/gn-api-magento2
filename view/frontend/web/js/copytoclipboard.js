window.onload = function () {

    if (document.getElementById('clickMe')) {
        let button = document.getElementById('clickMe');

        button.addEventListener('click', function (e) {
          e.preventDefault();
          document.execCommand('copy', false, document.getElementById('select-this').select());
        });
    }

}