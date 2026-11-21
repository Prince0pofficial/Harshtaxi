const menuBtn = document.getElementById("menuBtn");
const navigation = document.getElementById("navigation");

menuBtn.addEventListener("click", function () {
  navigation.classList.toggle("active");
});

document.querySelectorAll("#navigation a").forEach(function(link) {
  link.addEventListener("click", function() {
    navigation.classList.remove("active");
  });
});

// Prevent selecting a past journey date
const dateInput = document.getElementById("journeyDate");

const today = new Date();
const localDate = new Date(
  today.getTime() - today.getTimezoneOffset() * 60000
).toISOString().split("T")[0];

dateInput.min = localDate;
