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
    <button id="addRootObjectiveBtn">+ Objectiu estratègic</button>
  </header>

  <main>
    <table id="boardTable">
      <thead>
        <tr id="headerRow"></tr>
      </thead>
      <tbody id="boardBody"></tbody>
    </table>
  </main>

  <template id="objectiveControlsTpl">
    <div class="objective-controls">
      <button data-action="add-child" title="Subobjectiu">+Sub</button>
      <button data-action="move-up" title="Pujar">↑</button>
      <button data-action="move-down" title="Baixar">↓</button>
      <button data-action="delete" title="Eliminar">✕</button>
    </div>
  </template>

  <dialog id="cardDialog">
    <form method="dialog" id="cardForm">
      <h3 id="cardDialogTitle">Card</h3>
      <label>Títol
        <input name="title" id="cardTitle" maxlength="160" required>
      </label>
      <label>Descripció
        <textarea name="description" id="cardDescription" rows="3"></textarea>
      </label>
      <label id="yearlyCodeRow">Codi yearly (únic)
        <input name="code" id="cardCode" maxlength="50" placeholder="p.ex. YG-001">
      </label>
      <label id="linkedCodeRow">Codi yearly vinculat
        <select name="linked_yearly_code" id="cardLinkedCode"></select>
      </label>
      <menu>
        <button value="cancel">Cancel·lar</button>
        <button id="cardSaveBtn" value="default">Guardar</button>
      </menu>
    </form>
  </dialog>

  <script src="app.js"></script>
</body>
</html>
