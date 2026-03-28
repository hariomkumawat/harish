<!DOCTYPE html>
<html>
<head>
<title>Dukan Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
background:#f4f6f9;
}

/* Sidebar */
.sidebar{
height:100vh;
background:white;
border-right:1px solid #e5e7eb;
padding-top:10px;
}

.sidebar h4{
font-weight:600;
padding:15px;
}

/* links */
.sidebar a{
display:flex;
align-items:center;
gap:10px;
padding:12px 18px;
color:#555;
text-decoration:none;
border-radius:10px;
margin:5px 10px;
transition:0.2s;
}

.sidebar a:hover{
background:#f1f5f9;
color:#0d6efd;
}

.sidebar a.active{
background:#e7f1ff;
color:#0d6efd;
font-weight:500;
}

/* icon */
.sidebar i{
font-size:18px;
}

/* content */
.content{
padding:25px;
}
</style>

</head>
<body>

<div class="container-fluid">
<div class="row">

<div class="col-md-2 sidebar">
<h4>Harish ji ki Dukan</h4>

<a href="../index.php" class="active">
<i class="bi bi-speedometer2"></i> Dashboard
</a>

<a href="pages/sales.php">
<i class="bi bi-cart"></i> Sales
</a>

<a href="products.php">
<i class="bi bi-box-seam"></i> Products
</a>

<a href="stock.php">
<i class="bi bi-stack"></i> Stock
</a>

<a href="employees.php">
<i class="bi bi-people"></i> Employees
</a>

<a href="emi.php">
<i class="bi bi-cash-coin"></i> EMI
</a>

<a href="reports.php">
<i class="bi bi-bar-chart"></i> Reports
</a>

</div>

<div class="col-md-10 content">