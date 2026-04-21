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

  <script src="app.js"></script>
</body>
</html>
