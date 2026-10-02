<?php
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
$user=$_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects — EcoLoop</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/loop.css">
</head>
<body>
    <header class="topbar"><a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/favicon.svg" alt=""> EcoLoop</a>
        <nav aria-label="Main navigation"><a class="section-link" href="dashboard.php">Marketplace</a><button class="section-link active" data-view="studio">Projects</button><button data-view="projects">Mine</button><button data-view="credits">Credits</button></nav>
        <span class="account"><span class="status-dot"></span><?php echo htmlspecialchars($user['username']); ?></span>
    </header>
    <div id="demo-bar" class="demo-bar" hidden><span>DEMO COMMUNITY · Sample goods and transactions</span><label>Try a role <select id="demo-role"><option value="maker">Pratyush · Maker</option><option value="asha">Asha · Cardboard</option><option value="kabir">Kabir · Tubes</option><option value="mira">Mira · Fabric</option><option value="organiser">Community organiser</option></select></label></div>
    <div id="notice" class="notice" role="status" aria-live="polite" hidden></div>
    <main>
        <section class="view" id="view-studio">
            <section class="studio-hero">
                <div class="hero-copy"><div class="new-label"><span class="status-dot"></span> BUILD WITH WHAT IS ALREADY HERE</div>
                    <h1>Make more.<br><span>Waste less.</span></h1>
                    <p>Turn nearby materials into your next idea.</p>
                    <div class="button-row"><a href="#workspace" class="button primary">Start a project <span>↗</span></a><button data-view="projects" class="text-link">View my projects</button></div>
                </div>
                <div class="loop-scene" aria-label="Cardboard, tubes and fabric from three people become one project; credits return to contributors">
                    <div class="scene-grid"></div><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
                    <div class="material-float float-card"><div class="material-art cardboard"><i></i><i></i><i></i></div><span>CARDBOARD</span><small>Asha · 20 cr</small></div>
                    <div class="material-float float-tube"><div class="material-art tubes"><i></i><i></i><i></i></div><span>TUBES</span><small>Kabir · 15 cr</small></div>
                    <div class="material-float float-fabric"><div class="material-art fabric"><i></i><i></i></div><span>FABRIC</span><small>Mira · 25 cr</small></div>
                    <div class="loop-center"><span class="loop-symbol">↻</span><strong>One idea.<br>Shared value.</strong></div>
                    <div class="scene-output"><span>↳</span> READY TO BUILD <b>↗</b></div>
                </div>
            </section>
            <div class="value-strip"><div><span class="mini-loop">↻</span><strong>Owners approve.<br>Makers build.</strong></div><p>Contributors receive credits after collection.</p><button data-view="credits" class="text-link">See credits ↗</button></div>
            <section id="workspace" class="workspace-section">
                <div class="section-heading"><div><span class="eyebrow">START HERE</span><h2>Choose an idea.</h2></div></div>
                <div class="templates" aria-label="Project templates">
                    <button class="template active" data-template="desk"><span class="template-num">01</span><span><strong>Desktop organiser</strong><small>Cardboard, tubes &amp; fabric</small></span><span>↗</span></button>
                    <button class="template" data-template="display"><span class="template-num">02</span><span><strong>Exhibition display</strong><small>Give your ideas a stage</small></span><span>↗</span></button>
                    <button class="template" data-template="custom"><span class="template-num">03</span><span><strong>Something of your own</strong><small>Start with a blank canvas</small></span><span>+</span></button>
                </div>
                <form id="project-form" class="builder">
                    <section class="project-info"><div class="panel-title"><span>01</span><h3>The idea</h3></div>
                        <label>Project name<input name="name" id="project-name" maxlength="100" required></label>
                        <label>What are you making?<textarea name="description" id="project-description" rows="4" maxlength="2000" required></textarea></label>
                        <label>Collect by<input type="date" name="deadline" id="project-deadline" required></label>
                        <div class="small-note"><span>⌖</span><p>Matches stay within your community: <strong><?php echo htmlspecialchars($user['pincode']); ?></strong>. Arrange collection with your organiser.</p></div>
                        <button type="button" id="save-draft" class="button secondary full">Save as draft</button>
                    </section>
                    <section class="requirements-panel"><div class="panel-title"><span>02</span><h3>Materials</h3><small id="requirement-count"></small></div><div id="requirements"></div><button type="button" id="add-requirement" class="add-row">+ Add a material</button><button type="submit" class="button primary full" id="match-button">Find my materials <span>↗</span></button></section>
                    <aside class="kit-panel"><div class="panel-title"><span>03</span><h3>Your kit</h3><span class="live-label">LIVE</span></div><div id="kit-preview" aria-live="polite"><div class="kit-empty"><div class="empty-box">⌑</div><h3>A little of this.<br>A little of that.</h3><p>Your community's materials will come together here.</p></div></div></aside>
                </form>
            </section>
            <section class="inventory-section"><div class="section-heading"><div><span class="eyebrow">AVAILABLE NEARBY</span><h2>Ready to be reused.</h2></div><button data-view="supply" class="text-link">Offer materials ↗</button></div><div id="inventory-grid" class="inventory-grid"></div></section>
            <section class="closing"><h2>Build it.<br><span>Keep it moving.</span></h2><a href="dashboard.php" class="button secondary">Marketplace ↗</a></section>
        </section>
        <section class="view" id="view-projects" hidden><div class="section-heading"><div><span class="eyebrow">IDEAS IN MOTION</span><h1>My projects.</h1><p class="muted">Your builds, supplier requests and collection progress.</p></div><button data-view="studio" class="button primary">New project ↗</button></div><div id="project-list"></div></section>
        <section class="view" id="view-credits" hidden><div class="section-heading"><div><span class="eyebrow">VALUE COMES FULL CIRCLE</span><h1>A little give.<br>A lot of possibility.</h1></div></div><div class="credits-layout"><div class="balance-panel"><span class="eyebrow">YOUR LOOP BALANCE</span><div class="balance-ring"><strong id="available-balance">0</strong><span>credits available</span></div><div class="balance-detail"><span>Held for kits</span><strong id="held-balance">0 cr</strong></div><p class="micro">Credits are for community exchanges. No cash conversion, expiry or investment promise.</p></div><div><h2>Every contribution should count.</h2><p class="muted">Your unused geometry box could become someone's favourite find. An organiser checks useful goods into the community shelf, and you receive the agreed value.</p><form id="contribution-form" class="contribution-form"><label>What can you contribute?<input name="title" placeholder="A complete geometry box" maxlength="100" required></label><label>Condition and details<textarea name="description" placeholder="Describe what is included and why it is useful." maxlength="1000" required></textarea></label><div class="form-pair"><label>Proposed credits<input name="value" type="number" min="1" max="10000" required></label><button class="button primary" type="submit">Offer for review ↗</button></div><p class="micro">Posting earns no credits. Physical acceptance is required. The organiser can decline unsuitable stock.</p></form></div></div><div class="section-heading"><h2>The community shelf.</h2><span class="micro">Choose something useful. Collect with the organiser.</span></div><div id="shelf-grid" class="inventory-grid"></div><div class="history-layout"><section><h3>Your contributions</h3><div id="contribution-list"></div></section><section><h3>The value trail</h3><div id="ledger-list"></div></section></div></section>
        <section class="view" id="view-supply" hidden><span class="eyebrow">MAKE SOMEONE'S NEXT IDEA POSSIBLE</span><h1>Your materials.<br>Your terms.</h1><p class="muted">Opt a marketplace listing into project matching. You still approve each request before collection.</p><form id="material-form" class="material-form"><label>Marketplace listing<select name="item_id" id="material-item" required></select></label><p class="micro">Need to list something first? <a href="dashboard.php">Open My Items in the marketplace →</a></p><div class="form-pair"><label>Material type<input name="material" placeholder="cardboard" required maxlength="60" list="material-types"></label><label>Unit<select name="unit"><option>pack</option><option>piece</option><option>sheet</option><option>metre</option></select></label></div><div class="form-pair"><label>Available quantity<input name="quantity" type="number" min="1" max="10000" value="1" required></label><label>Condition<select name="condition"><option value="usable">Clean &amp; usable</option><option value="like-new">Like new</option></select></label></div><div class="form-pair"><label>Width (cm)<input name="width" type="number" min="0" max="10000" value="0" required></label><label>Height / length (cm)<input name="height" type="number" min="0" max="10000" value="0" required></label></div><div class="form-pair"><label>Exchange preference<select name="mode"><option value="credits">Trade credits</option><option value="gift">Gift voluntarily</option></select></label><label>Credits per unit<input name="credits" type="number" min="0" max="10000" value="5" required></label></div><label class="checkbox"><input name="enabled" type="checkbox" checked> Allow project requests for this listing</label><button class="button primary">Save my terms ↗</button></form></section>
        <section class="view" id="view-organiser" hidden><span class="eyebrow">COMMUNITY OPERATIONS</span><h1>Keep the loop moving.</h1><div id="reconciliation" class="reconciliation"></div><h2>Incoming contributions</h2><div id="intake-list"></div><h2>Collection desk</h2><div id="collection-list"></div><h2>Shelf handovers</h2><div id="redemption-list"></div></section>
        <datalist id="material-types"><option value="cardboard"><option value="tubes"><option value="fabric and string"><option value="fabric"><option value="paper"><option value="wood"></datalist>
    </main>
    <footer><a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/favicon.svg" alt=""> EcoLoop</a><span>Less waste. More possibility.</span><div><button data-view="supply">Offer materials</button><button id="organiser-nav" data-view="organiser" hidden>Organiser</button><button id="refresh-data">Refresh</button></div></footer>
    <dialog id="action-dialog"><form method="dialog"><button class="dialog-close" aria-label="Close">×</button></form><h2 id="dialog-title"></h2><p id="dialog-description" class="muted"></p><form id="action-form"><div id="dialog-fields"></div><button class="button primary full" id="dialog-submit">Confirm</button></form></dialog>
    <script src="assets/loop.js"></script>
</body>
</html>

