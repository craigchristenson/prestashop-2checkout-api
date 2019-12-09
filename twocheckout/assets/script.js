function successCallback(data) {
  $.blockUI({ message: 'Just a moment while we process your payment...' });
  var myForm = document.getElementById('twocheckoutCCForm');
  myForm.token.value = data.response.token.token;
  myForm.submit();
}

function errorCallback(data) {
  clearFields();
  if (data.errorCode === 200) {
    TCO.requestToken(successCallback, errorCallback, 'tcoCCForm');
  } else if(data.errorCode == 401) {
    $("#twocheckout_error_creditcard").show();
  } else {
    alert(data.errorMsg);
  }
}

$("#twocheckoutCCForm").submit(function (e) {
  e.preventDefault();
  $("#twocheckout_error_creditcard").hide();
  TCO.requestToken(successCallback, errorCallback, 'twocheckoutCCForm');
});

(function($) {
  $.QueryString = (function(a) {
    if (a == "") return {};
    var b = {};
    for (var i = 0; i < a.length; ++i){
      var p=a[i].split('=');
      if (p.length != 2) continue;
      b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
    }
    return b;
  })(window.location.search.substr(1).split('&'))
})(jQuery);

$('.numeric').on('blur', function () {
  this.value = this.value.replace(/[^0-9]/g, '');
});

function clearFields () {
  $('#ccNo').val('');
  $('#expMonth').val('');
  $('#expYear').val('');
  $('#cvv').val('');
}

$(document).ready(function() {
  if ($.QueryString["twocheckouterror"]) {
    $( "#twocheckout-error" ).show();
	$('.payment-options').find("input[data-module-name='2Checkout']").click();
  } else {
    $( "#twocheckout-error" ).hide();
  }
});