<?php include '../layout.php'; ?>

<h3>Quick Sale</h3>

<div class="row">

<div class="col-md-4">
<div class="card text-center p-3 product-card" onclick="selectProduct(this,'Pav Vada')">
<img src="https://cdn-icons-png.flaticon.com/512/5787/5787100.png" width="80">
<h5 class="mt-2">Pav Vada</h5>
</div>
</div>

<div class="col-md-4">
<div class="card text-center p-3 product-card" onclick="selectProduct(this,'Sandwich')">
<img src="https://cdn-icons-png.flaticon.com/512/3075/3075977.png" width="80">
<h5 class="mt-2">Sandwich</h5>
</div>
</div>

<div class="col-md-4">
<div class="card text-center p-3 product-card" onclick="selectProduct(this,'Khichdi')">
<img src="https://cdn-icons-png.flaticon.com/512/1046/1046784.png" width="80">
<h5 class="mt-2">Khichdi</h5>
</div>
</div>

</div>

<hr>

<form>
<div class="row">

<div class="col-md-4">
<label>Selected Product</label>
<input type="text" id="product" class="form-control" readonly>
</div>

<div class="col-md-2">
<label>Qty</label>
<input type="number" class="form-control" placeholder="Qty">
</div>

<div class="col-md-2 mt-4">
<button class="btn btn-primary">Add Sale</button>
</div>

</div>
</form>

<style>
.product-card{
cursor:pointer;
transition:0.3s;
border:2px solid transparent;
box-shadow:0 10px 25px rgba(0,0,0,0.08);
}

.product-card:hover{
transform:scale(1.05);
}

/* selected border */
.product-selected{
background:#013682;
color:#fff;
}
</style>

<script>
function selectProduct(el,name){

// remove previous selection
document.querySelectorAll('.product-card').forEach(card=>{
card.classList.remove('product-selected');
});

// add new selection
el.classList.add('product-selected');

document.getElementById('product').value = name;
}
</script>

</div></div></div></body></html>