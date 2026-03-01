document.getElementById("photo").addEventListener("change", function (e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function (evt) {
    const img = new Image();
    img.onload = function () {
      // draw to canvas to ensure circle cropping
      const canvas = document.createElement("canvas");
      const size = 80;
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext("2d");
      ctx.beginPath();
      ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
      ctx.closePath();
      ctx.clip();
      // draw centered
      let sx = 0,
        sy = 0,
        sSize = Math.min(img.width, img.height);
      if (img.width > img.height) {
        sx = (img.width - img.height) / 2;
      } else {
        sy = (img.height - img.width) / 2;
      }
      ctx.drawImage(img, sx, sy, sSize, sSize, 0, 0, size, size);
      const dataUrl = canvas.toDataURL("image/png");
      showPreview(dataUrl);
    };
    img.src = evt.target.result;
  };
  reader.readAsDataURL(file);
});

function showPreview(photoUrl) {
  const fullName = document.getElementById("full_name").value;
  const post = document.getElementById("post").value;
  const matric = document.getElementById("matric_no").value;

  const previewDiv = document.getElementById("preview");
  previewDiv.innerHTML = "";
  const card = document.createElement("div");
  card.className = "card";
  const photoDiv = document.createElement("div");
  photoDiv.className = "photo";
  const img = document.createElement("img");
  img.src = photoUrl;
  photoDiv.appendChild(img);
  card.appendChild(photoDiv);
  const info = document.createElement("div");
  info.className = "info";
  info.innerHTML = `<h2>${fullName}</h2><p>${matric}</p><p>${post}</p>`;
  card.appendChild(info);
  previewDiv.appendChild(card);
}

// update preview on field changes
["full_name", "post", "matric_no"].forEach((id) => {
  document.getElementById(id).addEventListener("input", function () {
    const photoImg = document.querySelector("#preview .photo img");
    if (photoImg) showPreview(photoImg.src);
  });
});
