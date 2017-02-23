$(document).ready(function() {
    $('input#requiredByDate, input#last-interest-date').datepicker({
    format: "dd.mm.yyyy",
    weekStart: 1,
    language: "cs"
  });
});