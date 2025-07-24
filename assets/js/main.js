// Custom alert fadeout (optional)
setTimeout(() => {
  const alerts = document.querySelectorAll('.alert-dismissible');
  alerts.forEach(alert => {
    alert.classList.add('fade');
    setTimeout(() => alert.remove(), 1000);
  });
}, 5000);
