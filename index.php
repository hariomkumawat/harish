<?php include 'layout.php'; ?>

<h3>Dashboard</h3>

<!-- Product Selection -->
<div class="row mb-4">

<div class="col-md-4">
<div class="card text-center p-3 product-card product-selected" 
     onclick="selectProduct(this,'Pav Vada')" id="defaultProduct">
<img src="https://cdn-icons-png.flaticon.com/512/5787/5787100.png" width="70">
<h5 class="mt-2">Pav Vada</h5>
</div>
</div>

<div class="col-md-4">
<div class="card text-center p-3 product-card" onclick="selectProduct(this,'Sandwich')">
<img src="https://cdn-icons-png.flaticon.com/512/3075/3075977.png" width="70">
<h5 class="mt-2">Sandwich</h5>
</div>
</div>

<div class="col-md-4">
<div class="card text-center p-3 product-card" onclick="selectProduct(this,'Khichdi')">
<img src="https://cdn-icons-png.flaticon.com/512/1046/1046784.png" width="70">
<h5 class="mt-2">Khichdi</h5>
</div>
</div>

</div>

<!-- Sales Cards -->
<div class="row">

<div class="col-md-4">
<div class="stat-card bg1">
<div class="icon">₹</div>
<div class="title">Today Sale</div>
<div class="value" id="today">₹0</div>
</div>
</div>

<div class="col-md-4">
<div class="stat-card bg2">
<div class="icon">📊</div>
<div class="title">Weekly Sale</div>
<div class="value" id="weekly">₹0</div>
</div>
</div>

<div class="col-md-4">
<div class="stat-card bg3">
<div class="icon">📅</div>
<div class="title">Monthly Sale</div>
<div class="value" id="monthly">₹0</div>
</div>
</div>

</div>

<!-- From Uiverse.io by Yaya12085 --> 
<br><br>
<div class="card">
    <div class="title">
        <span>
          Today Sales
        </span>
    </div>
    <div class="data">
        <p>
         ₹ 39,500 
        </p>
        
        <div class="range">
            <div class="fill">
            </div>
        </div>
    </div>
</div>


<style>
.product-card{
cursor:pointer;
border:2px solid transparent;
transition:0.3s;
}
.product-card:hover{
transform:scale(1.05);
}
.product-selected{
border:2px solid #0d6efd;
background:#eef5ff;
}
.stat-card{
padding:20px;
border-radius:15px;
color:#333;
position:relative;
overflow:hidden;
box-shadow:0 10px 25px rgba(0,0,0,0.08);
transition:0.3s;
}

.stat-card:hover{
transform:translateY(-5px);
}

.stat-card .icon{
width:45px;
height:45px;
background:rgba(255,255,255,0.6);
border-radius:10px;
display:flex;
align-items:center;
justify-content:center;
font-size:20px;
margin-bottom:10px;
}

.stat-card .title{
font-size:13px;
letter-spacing:1px;
color:#555;
}

.stat-card .value{
font-size:28px;
font-weight:bold;
}

/* gradients */
.bg1{
background:linear-gradient(135deg,#e0f7fa,#e1f5fe);
}

.bg2{
background:linear-gradient(135deg,#ede7f6,#e3f2fd);
}

.bg3{
background:linear-gradient(135deg,#fff3e0,#ffe0b2);
}

/* From Uiverse.io by Yaya12085 */ 
.card {
  padding: 1rem;
  background-color: #fff;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  max-width: 320px;
  border-radius: 20px;
}

.title {
  display: flex;
  align-items: center;
}

.title span {
  position: relative;
  background-color: #10B981;
  padding-left: 20px;
  width: 7.5rem;
  height: 1.7rem;
  border-radius: 9999px;
  color:#fff;
}

.title span svg {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #ffffff;
  height: 1rem;
}

.title-text {
  margin-left: 0.5rem;
  color: #374151;
  font-size: 18px;
}

.percent {
  margin-left: 0.5rem;
  color: #02972f;
  font-weight: 600;
  display: flex;
}

.data {
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}

.data p {
  margin-top: 1rem;
  margin-bottom: 1rem;
  color: #1F2937;
  font-size: 2.25rem;
  line-height: 2.5rem;
  font-weight: 700;
  text-align: left;
}

.data .range {
  position: relative;
  background-color: #E5E7EB;
  width: 100%;
  height: 0.5rem;
  border-radius: 0.25rem;
}

.data .range .fill {
  position: absolute;
  top: 0;
  left: 0;
  background-color: #10B981;
  width: 76%;
  height: 100%;
  border-radius: 0.25rem;
}
</style>

<script>
function selectProduct(el,name){

document.querySelectorAll('.product-card').forEach(card=>{
card.classList.remove('product-selected');
});

el.classList.add('product-selected');

// dummy values (backend later)
if(name === 'Pav Vada'){
document.getElementById('today').innerHTML = "₹500";
document.getElementById('weekly').innerHTML = "₹3200";
document.getElementById('monthly').innerHTML = "₹12500";
}

if(name === 'Sandwich'){
document.getElementById('today').innerHTML = "₹300";
document.getElementById('weekly').innerHTML = "₹2100";
document.getElementById('monthly').innerHTML = "₹9000";
}

if(name === 'Khichdi'){
document.getElementById('today').innerHTML = "₹200";
document.getElementById('weekly').innerHTML = "₹1400";
document.getElementById('monthly').innerHTML = "₹6000";
}

window.onload = function(){
selectProduct(document.getElementById('defaultProduct'),'Pav Vada');
}

}
</script>

</div></div></div></body></html>