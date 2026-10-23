<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('admin');

$active = 'categories';
$error = null;
$editingId = max(0, (int) ($_GET['edit'] ?? 0));

function category_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'category';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $action = (string) ($_POST['action'] ?? 'create');
    $categoryId = max(0, (int) ($_POST['category_id'] ?? 0));

    if ($action === 'toggle') {
        $newStatus = ($_POST['new_status'] ?? 'inactive') === 'active' ? 'active' : 'inactive';
        if ($categoryId > 0) {
            db()->prepare('UPDATE categories SET status = ? WHERE id = ?')->execute([$newStatus, $categoryId]);
            flash('success', 'Category status updated.');
        }
        redirect('/admin/categories.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $parentId = max(0, (int) ($_POST['parent_id'] ?? 0));
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

    if ($name === '') {
        $error = 'Category name is required.';
    } elseif ($categoryId > 0 && $categoryId === $parentId) {
        $error = 'A category cannot be its own parent.';
    } else {
        $pdo = db();
        $parentId = $parentId > 0 ? $parentId : null;

        if ($action === 'edit' && $categoryId > 0) {
            $check = $pdo->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
            $check->execute([$categoryId]);
            if (!$check->fetchColumn()) {
                $error = 'The category you are trying to edit no longer exists.';
            } else {
                // Prevent circular parent relationships by ensuring the selected parent is not a descendant.
                $candidate = $parentId;
                $visited = [];
                while ($candidate !== null && !isset($visited[$candidate])) {
                    $visited[$candidate] = true;
                    if ((int)$candidate === $categoryId) {
                        $error = 'Invalid parent category. This would create a category loop.';
                        break;
                    }
                    $stmt = $pdo->prepare('SELECT parent_id FROM categories WHERE id = ? LIMIT 1');
                    $stmt->execute([(int)$candidate]);
                    $candidate = $stmt->fetchColumn();
                    $candidate = $candidate === false ? null : ($candidate === null ? null : (int)$candidate);
                }
            }

            if (!$error) {
                $pdo->prepare(
                    'UPDATE categories SET parent_id = ?, name = ?, description = ?, status = ?, sort_order = ? WHERE id = ?'
                )->execute([$parentId, $name, $description ?: null, $status, $sortOrder, $categoryId]);
                flash('success', 'Category updated successfully.');
                redirect('/admin/categories.php');
            }
        } else {
            $baseSlug = category_slug($name);
            $slug = $baseSlug;
            $suffix = 2;
            $check = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
            while (true) {
                $check->execute([$slug]);
                if (!$check->fetch()) {
                    break;
                }
                $slug = $baseSlug . '-' . $suffix++;
            }

            $pdo->prepare(
                'INSERT INTO categories (parent_id, name, slug, description, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$parentId, $name, $slug, $description ?: null, $status, $sortOrder]);
            flash('success', 'Category created successfully.');
            redirect('/admin/categories.php');
        }
    }
}

$parents = db()->query(
    "SELECT id, name
     FROM categories
     WHERE parent_id IS NULL
     ORDER BY sort_order, name"
)->fetchAll();

$categories = db()->query(
    "SELECT c.id, c.parent_id, c.name, c.slug, c.status, c.sort_order,
            parent.name AS parent_name,
            COUNT(DISTINCT pc.product_id) AS product_count
     FROM categories c
     LEFT JOIN categories parent ON parent.id = c.parent_id
     LEFT JOIN product_categories pc ON pc.category_id = c.id
     GROUP BY c.id
     ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name"
)->fetchAll();

$editCategory = null;
if ($editingId > 0) {
    $editStmt = db()->prepare('SELECT id, parent_id, name, description, status, sort_order FROM categories WHERE id = ? LIMIT 1');
    $editStmt->execute([$editingId]);
    $editCategory = $editStmt->fetch() ?: null;
    if (!$editCategory) {
        $editingId = 0;
    }
}
?>
<!doctype html>
<html lang="en">
<head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categories · DogoFlow Admin</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/responsive.css')) ?>">
<style>
.categories-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px}.category-header-actions{display:flex;align-items:center;gap:10px}.category-total{padding:9px 12px;border:1px solid #E2E8F0;border-radius:10px;background:#fff;color:#64748B;font-size:.78rem}.category-total strong{color:#0B2545}.category-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.category-stats article{background:#fff;border:1px solid #E2E8F0;border-radius:15px;padding:16px 18px;box-shadow:0 5px 18px rgba(7,26,51,.04)}.category-stats span{display:block;color:#64748B;font-size:.72rem;font-weight:700}.category-stats strong{display:block;color:#0B2545;font-size:1.45rem;margin-top:4px}.categories-workspace{display:grid;grid-template-columns:minmax(300px,.82fr) minmax(0,1.8fr);gap:18px}.category-form-panel,.category-list-panel{overflow:hidden}.category-form-kicker{display:block;color:#0088EE;text-transform:uppercase;letter-spacing:.13em;font-size:.64rem;font-weight:900;margin-bottom:4px}.category-form-panel .panel-head-df h2,.category-list-head h2{margin:0;color:#0B2545;font-size:1.05rem}.category-form-panel .panel-head-df p,.category-list-head p{margin:5px 0 0;color:#64748B;font-size:.76rem;line-height:1.5}.category-cancel{font-size:.75rem;color:#0088EE;font-weight:800;text-decoration:none}.category-form{display:grid;gap:14px}.category-form label{display:grid;gap:7px;color:#334155;font-size:.74rem;font-weight:800}.category-form input,.category-form select,.category-form textarea{width:100%;border:1px solid #DCE5EF;background:#fff;border-radius:10px;padding:11px 12px;color:#172033;font:inherit;font-size:.82rem;outline:none}.category-form input:focus,.category-form select:focus,.category-form textarea:focus,.category-search input:focus{border-color:#7CCBFA;box-shadow:0 0 0 3px rgba(0,163,255,.1)}.category-form textarea{resize:vertical}.category-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.category-submit{width:100%;margin-top:2px}.category-list-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px}.category-search{display:flex;align-items:center;gap:8px;border:1px solid #DCE5EF;border-radius:10px;padding:0 11px;height:40px;background:#fff;min-width:210px}.category-search span{color:#94A3B8;font-size:1.1rem}.category-search input{border:0;outline:0;width:100%;font-size:.76rem;color:#172033}.category-table-wrap{overflow:auto}.category-table{min-width:760px}.category-table th{white-space:nowrap}.category-name-cell{display:flex;align-items:center;gap:10px}.category-icon{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;background:#EAF7FF;color:#0088EE;font-size:.62rem;font-weight:900}.category-name-cell strong{display:block;color:#172033}.category-name-cell small{display:block;color:#94A3B8;margin-top:2px}.category-status{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:.68rem;font-weight:850}.category-status.is-active{background:#ECFDF5;color:#047857}.category-status.is-inactive{background:#F1F5F9;color:#64748B}.category-actions{display:flex;gap:6px;justify-content:flex-end}.category-actions form{margin:0}.category-actions .btn-sm{min-height:34px;padding:7px 10px;font-size:.68rem}.category-actions .danger{background:#FFF1F2!important;color:#BE123C!important;border:1px solid #FECDD3!important}.category-empty{text-align:center;padding:42px 20px;color:#64748B}.category-empty strong{color:#172033}.category-empty p{font-size:.76rem;margin:5px 0 0}.category-alert{margin-bottom:15px}.admin-main > .admin-content{padding-top:30px!important}.hidden{display:none!important}@media(max-width:1100px){.categories-workspace{grid-template-columns:1fr}.category-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.categories-heading,.category-list-head{align-items:flex-start;flex-direction:column}.category-header-actions{width:100%;justify-content:space-between}.category-stats{grid-template-columns:1fr 1fr}.category-search{width:100%;min-width:0}.category-form-row{grid-template-columns:1fr}}
</style></head>
<body class="admin-shell">
<?php require __DIR__ . '/../partials/admin_sidebar.php'; ?>
<div class="admin-main">
    <?php require __DIR__ . '/../partials/admin_topbar.php'; ?>

    <main class="admin-content">
        <div class="page-heading categories-heading">
            <div>
                <span class="eyebrow">Marketplace</span>
                <h1>Categories</h1>
                <p>Build a clean product taxonomy that sellers and customers can actually use.</p>
            </div>
            <div class="category-header-actions">
                <span class="category-total"><strong><?= number_format(count($categories)) ?></strong> total</span>
                <a class="btn-df secondary" href="<?= e(url('/categories.php')) ?>" target="_blank">View marketplace</a>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-danger category-alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($message = flash('success')): ?><div class="alert alert-success category-alert"><?= e($message) ?></div><?php endif; ?>

        <div class="category-stats">
            <article><span>All categories</span><strong><?= number_format(count($categories)) ?></strong></article>
            <article><span>Active</span><strong><?= number_format(count(array_filter($categories, fn($c) => $c['status'] === 'active'))) ?></strong></article>
            <article><span>Top level</span><strong><?= number_format(count(array_filter($categories, fn($c) => $c['parent_id'] === null))) ?></strong></article>
            <article><span>Products assigned</span><strong><?= number_format(array_sum(array_map(fn($c) => (int)$c['product_count'], $categories))) ?></strong></article>
        </div>

        <div class="categories-workspace">
            <section class="panel-df category-form-panel">
                <div class="panel-head-df">
                    <div>
                        <span class="category-form-kicker"><?= $editCategory ? 'Edit category' : 'Create category' ?></span>
                        <h2><?= $editCategory ? 'Update category' : 'Add a category' ?></h2>
                        <p><?= $editCategory ? 'Change the name, parent, status or display order.' : 'Create a top-level category or nest it under an existing category.' ?></p>
                    </div>
                    <?php if ($editCategory): ?><a class="category-cancel" href="<?= e(url('/admin/categories.php')) ?>">Cancel</a><?php endif; ?>
                </div>
                <div class="panel-body-df">
                    <form method="post" class="category-form">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'create' ?>">
                        <?php if ($editCategory): ?><input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>"><?php endif; ?>
                        <label>Category name<input type="text" name="name" maxlength="120" required value="<?= e((string)($editCategory['name'] ?? '')) ?>" placeholder="e.g. Electronics"></label>
                        <label>Parent category<select name="parent_id"><option value="0">Top-level category</option><?php foreach ($parents as $parent): ?><?php if (!$editCategory || (int)$parent['id'] !== (int)$editCategory['id']): ?><option value="<?= (int)$parent['id'] ?>" <?= ($editCategory && (int)($editCategory['parent_id'] ?? 0) === (int)$parent['id']) ? 'selected' : '' ?>><?= e($parent['name']) ?></option><?php endif; ?><?php endforeach; ?></select></label>
                        <label class="full">Description<textarea name="description" rows="4" maxlength="500" placeholder="Short description shown to help organize the marketplace."><?= e((string)($editCategory['description'] ?? '')) ?></textarea></label>
                        <div class="category-form-row"><label>Status<select name="status"><option value="active" <?= (!$editCategory || $editCategory['status'] === 'active') ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($editCategory && $editCategory['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option></select></label><label>Sort order<input type="number" name="sort_order" min="0" value="<?= (int)($editCategory['sort_order'] ?? 0) ?>"></label></div>
                        <button class="btn-df primary category-submit" type="submit"><?= $editCategory ? 'Save changes' : 'Create category' ?></button>
                    </form>
                </div>
            </section>

            <section class="panel-df category-list-panel">
                <div class="panel-head-df category-list-head">
                    <div><span class="category-form-kicker">Catalog structure</span><h2>Your categories</h2><p>Use status and hierarchy to control what appears across the marketplace.</p></div>
                    <div class="category-search"><span>⌕</span><input id="categorySearch" type="search" placeholder="Search categories..."></div>
                </div>
                <div class="category-table-wrap">
                    <table class="table-df category-table" id="categoryTable">
                        <thead><tr><th>Category</th><th>Parent</th><th>Products</th><th>Status</th><th>Order</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr data-category-search="<?= e(strtolower($category['name'].' '.($category['parent_name'] ?? '').' '.$category['slug'])) ?>">
                                <td><div class="category-name-cell"><span class="category-icon"><?= $category['parent_id'] === null ? '◆' : '↳' ?></span><div><strong><?= e($category['name']) ?></strong><small>/<?= e($category['slug']) ?></small></div></div></td>
                                <td><?= e($category['parent_name'] ?: 'Top level') ?></td>
                                <td><strong><?= number_format((int)$category['product_count']) ?></strong></td>
                                <td><span class="category-status <?= $category['status'] === 'active' ? 'is-active' : 'is-inactive' ?>"><?= e(ucfirst($category['status'])) ?></span></td>
                                <td><?= (int)$category['sort_order'] ?></td>
                                <td><div class="category-actions"><a class="btn-df secondary btn-sm" href="<?= e(url('/admin/categories.php?edit='.(int)$category['id'])) ?>">Edit</a><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>"><input type="hidden" name="new_status" value="<?= $category['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="btn-df <?= $category['status'] === 'active' ? 'danger' : 'primary' ?> btn-sm" type="submit"><?= $category['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form></div></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$categories): ?><tr><td colspan="6"><div class="category-empty"><strong>No categories yet.</strong><p>Create your first category to start organizing products.</p></div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <div id="categoryNoResults" class="category-empty hidden"><strong>No matching categories.</strong><p>Try another search term.</p></div>
                </div>
            </section>
        </div>
    </main>
</div>
<script>
(() => {
  const input=document.getElementById('categorySearch');
  const rows=[...document.querySelectorAll('#categoryTable tbody tr[data-category-search]')];
  const empty=document.getElementById('categoryNoResults');
  input?.addEventListener('input',()=>{
    const q=input.value.trim().toLowerCase(); let visible=0;
    rows.forEach(row=>{const show=!q || row.dataset.categorySearch.includes(q); row.style.display=show?'':'none'; if(show) visible++;});
    if(empty) empty.classList.toggle('hidden',visible!==0 || rows.length===0);
  });
})();
</script></body>
</html>
