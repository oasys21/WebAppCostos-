<?php
declare(strict_types=1);
$base = isset($this) && isset($this->cfg['BASE_URL']) ? rtrim($this->cfg['BASE_URL'], '/') : '';
?>
</main>
<footer class="bg-dark text-light mt-4">
  <div class="container py-3">
    <div class="row g-2 small">
      <div class="col-md-6">
        <strong>RHGlobal - OASYS spa</strong><br>
        Dirección: Isabel Riquelme 229, Illapel, Coquimbo, Chile<br>
        Email: admin@rhglobal.cl, admin@oasys.cl · Fono: +569 8828 3756
      </div>
      <div class="col-md-6 text-md-end">
        <span>© <?= date('Y') ?> · RHGlobal-OASYS spa</span>
      </div>
    </div>
  </div>
</footer>
<script src="<?= htmlspecialchars($base) ?>/public/js/bootstrap.bundle.min.js"></script>
<!-- Si necesitas jQuery global para algunos módulos, descomenta la siguiente línea y coloca jquery.min.js en /public/js -->
<script src="<?= htmlspecialchars($base) ?>/public/js/jquery.min.js"></script>
</body>
</html>
