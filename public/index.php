<!doctype html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hoshin Board</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <h1>Hoshin Board</h1>
    <button id="addRootObjectiveBtn">+ Objectiu</button>
  </header>

  <main>
    <table id="boardTable">
      <thead><tr id="headerRow"></tr></thead>
      <tbody id="boardBody"></tbody>
    </table>
  </main>

  <aside id="cardEditor" class="hidden">
    <div class="editor-panel">
      <h3 id="editorTitle">Card</h3>

      <label>Títol
        <input id="fieldTitle" maxlength="160">
      </label>

      <label>Descripció
        <textarea id="fieldDescription" rows="4"></textarea>
      </label>

      <label id="codeRow">Codi yearly
        <input id="fieldCode" maxlength="50">
      </label>

      <label id="linkedRow">Codi yearly vinculat
        <select id="fieldLinked"></select>
      </label>

      <div class="editor-actions">
        <button id="cancelCardBtn">Cancel·lar</button>
        <button id="saveCardBtn">Guardar</button>
      </div>
    </div>
  </aside>

  <script src="app.js"></script>
</body>
</html>
