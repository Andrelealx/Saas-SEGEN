<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';
require __DIR__.'/../../inc/header.php'; 
 

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM employees WHERE id=?");
$st->execute([$id]);
$e = $st->fetch(); if(!$e){ http_response_code(404); exit('Não encontrado'); }

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check('_csrf');
  $sql="UPDATE employees SET registration=?,name=?,cpf=?,birth_date=?,email=?,phone=?,hire_date=?,department_id=?,position_id=?,base_shift=?,status=?,notes=? WHERE id=?";
  $p=$pdo->prepare($sql);
  $p->execute([$_POST['registration'],$_POST['name'],$_POST['cpf']?:NULL,$_POST['birth_date']?:NULL,
               $_POST['email']?:NULL,$_POST['phone']?:NULL,$_POST['hire_date']?:NULL,
               $_POST['department_id']?:NULL,$_POST['position_id']?:NULL,$_POST['base_shift']?:'Adm',
               $_POST['status']?:'ATIVO',$_POST['notes']?:NULL,$id]);
  set_flash("Funcionário atualizado!");
  header("Location: /modules/employees/"); exit;
}
$deps = $pdo->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();
$pos  = $pdo->query("SELECT id,name FROM positions ORDER BY name")->fetchAll();
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Editar Funcionário</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/app.css">
</head><body class="bg-gray-900 text-gray-100">
<header class="px-4 py-3 border-b border-gray-700 flex items-center justify-between bg-gray-800">
  <h1 class="font-bold">Editar Funcionário</h1>
  <nav class="space-x-3 text-sm">
    <a href="/modules/employees/" class="hover:text-orange-400">Voltar</a>
  </nav>
</header>
<main class="p-4 max-w-3xl">
  <?php flash(); ?>
  <form method="post" class="grid gap-3 md:grid-cols-2">
    <input type="hidden" name="_csrf" value="<?=h(csrf_token('_csrf'))?>">
    <div><label>Matrícula</label><input name="registration" required class="input" value="<?=h($e['registration'])?>"></div>
    <div><label>Nome</label><input name="name" required class="input" value="<?=h($e['name'])?>"></div>
    <div><label>CPF</label><input name="cpf" class="input" value="<?=h($e['cpf'])?>"></div>
    <div><label>Nascimento</label><input type="date" name="birth_date" class="input" value="<?=h($e['birth_date'])?>"></div>
    <div><label>E-mail</label><input type="email" name="email" class="input" value="<?=h($e['email'])?>"></div>
    <div><label>Telefone</label><input name="phone" class="input" value="<?=h($e['phone'])?>"></div>
    <div><label>Admissão</label><input type="date" name="hire_date" class="input" value="<?=h($e['hire_date'])?>"></div>
    <div><label>Departamento</label>
      <select name="department_id" class="select"><option value="">—</option>
        <?php foreach($deps as $d): ?><option value="<?=h($d['id'])?>" <?= $e['department_id']==$d['id']?'selected':'' ?>><?=h($d['name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label>Cargo</label>
      <select name="position_id" class="select"><option value="">—</option>
        <?php foreach($pos as $p): ?><option value="<?=h($p['id'])?>" <?= $e['position_id']==$p['id']?'selected':'' ?>><?=h($p['name'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label>Turno base</label>
      <select name="base_shift" class="select">
        <?php foreach(['Adm','12x36','24x72','Outro'] as $opt): ?>
          <option <?= $e['base_shift']===$opt?'selected':'' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Status</label>
      <select name="status" class="select">
        <?php foreach(['ATIVO','AFASTADO','LICENCA','FERIAS','INATIVO'] as $opt): ?>
          <option <?= $e['status']===$opt?'selected':'' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="md:col-span-2"><label>Observações</label><textarea name="notes" class="input" rows="4"><?=h($e['notes'])?></textarea></div>
    <div class="md:col-span-2"><button class="btn">Salvar</button></div>
  </form>
</main>
</body></html>

<?php require __DIR__.'/../../inc/footer.php'; ?>
