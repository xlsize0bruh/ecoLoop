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
        <nav aria-label="Main navigation"><a class="section-link" href="dashboard.php">Marketplace</a><button class="section-link active" data-view="studio">Projects</button><button data-view="projects">My projects</button><button data-view="credits">My credits</button></nav>
        <span class="account"><span class="status-dot"></span><?php echo htmlspecialchars($user['username']); ?></span>
    </header>
    <div id="demo-bar" class="demo-bar" hidden><span>DEMO COMMUNITY · Sample goods and transactions</span><label>Try a role <select id="demo-role"><option value="maker">Pratyush · Maker</option><option value="asha">Asha · Cardboard</option><option value="kabir">Kabir · Tubes</option><option value="mira">Mira · Fabric</option><option value="organiser">Community organiser</option></select></label></div>
    <div id="notice" class="notice" role="status" aria-live="polite" hidden></div>
    <main>
        <section class="view" id="view-studio">
            <div class="eyebrow breadcrumb">THE COMMUNITY, REASSEMBLED <span>01 / PROJECT STUDIO</span></div>
            <section class="studio-hero">
                <div class="hero-copy"><div class="new-label"><span class="status-dot"></span> A second life starts with an idea.</div>
                    <h1>Trade what you have.<br><span>Build what you need.</span></h1>
                    <p>Good ideas don't need new materials. Connect the things your community already has — and make something of your own.</p>
                    <div class="button-row"><a href="#workspace" class="button primary">Start a project <span>↗</span></a><a href="dashboard.php" class="text-link">Explore the marketplace →</a></div>
                    <div class="hero-foot"><span>01 Describe</span><i>—</i><span>02 Match</span><i>—</i><span>03 Build</span><i>—</i><span>04 Share</span></div>
                </div>
                <div class="loop-scene" aria-label="Cardboard, tubes and fabric from three people become one project; credits return to contributors">
                    <div class="scene-grid"></div><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
                    <div class="scene-caption eyebrow">MANY MATERIALS. ONE POSSIBILITY.</div>
                    <div class="material-float float-card"><div class="material-art cardboard"><i></i><i></i><i></i></div><span>CARDBOARD</span><small>Asha · +20 cr*</small></div>
                    <div class="material-float float-tube"><div class="material-art tubes"><i></i><i></i><i></i></div><span>TUBES</span><small>Kabir · +15 cr*</small></div>
                    <div class="material-float float-fabric"><div class="material-art fabric"><i></i><i></i></div><span>FABRIC + STRING</span><small>Mira · +25 cr*</small></div>
                    <div class="loop-center"><span class="loop-symbol">↻</span><strong>Made possible.<br>Together.</strong><small>HAVE · TRADE · BUILD · SHARE</small></div>
                    <div class="scene-output"><span>↳</span> YOUR NEXT CREATION <b>↗</b></div><div class="scene-note">*Illustrative supplier earnings after collection</div>
                </div>
            </section>
            <div class="value-strip"><div><span class="mini-loop">↻</span><strong>Good for the maker.<br>Worthwhile for the owner.</strong></div><p>Owners set their value, approve each request, and earn credits after collection. Those credits can become something they actually want.</p><button data-view="credits" class="text-link">Follow the value ↗</button></div>
            <section id="workspace" class="workspace-section">
                <div class="section-heading"><div><span class="eyebrow">FROM IDEA TO INVENTORY</span><h2>Your next project starts here.</h2></div><span class="micro">Same marketplace. New possibilities.</span></div>
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
                    <section class="requirements-panel"><div class="panel-title"><span>02</span><h3>The pieces</h3><small id="requirement-count"></small></div><p class="micro">Choose exact material types and units. Dimensions are minimums in centimetres.</p><div id="requirements"></div><button type="button" id="add-requirement" class="add-row">+ Add a material</button><button type="submit" class="button primary full" id="match-button">Find my materials <span>↗</span></button><p class="micro">No silent substitutions. Missing pieces stay visible.</p></section>
                    <aside class="kit-panel"><div class="panel-title"><span>03</span><h3>Your kit</h3><span class="live-label">LIVE</span></div><div id="kit-preview" aria-live="polite"><div class="kit-empty"><div class="empty-box">⌑</div><h3>A little of this.<br>A little of that.</h3><p>Your community's materials will come together here.</p></div></div></aside>
                </form>
            </section>
            <section class="inventory-section"><div class="section-heading"><div><span class="eyebrow">ALREADY OUT THERE</span><h2>Materials with more to give.</h2></div><button data-view="supply" class="text-link">Offer your materials ↗</button></div><div id="inventory-grid" class="inventory-grid"></div></section>
            <section class="closing"><span class="eyebrow">THE BEST MATERIAL IS THE ONE THAT ALREADY EXISTS.</span><h2>Make something.<br><span>Keep the loop going.</span></h2><p>Finish your project. Share what worked. List the leftovers for someone else's next idea.</p><a href="dashboard.php" class="button secondary">Back to the marketplace ↗</a></section>
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

