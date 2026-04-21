const API = './api.php';

const headerRow = document.getElementById('headerRow');
const boardBody = document.getElementById('boardBody');
const addRootObjectiveBtn = document.getElementById('addRootObjectiveBtn');
const controlsTpl = document.getElementById('objectiveControlsTpl');

let board = { columns: [], objectives: [], cards: [] };

async function api(action, method = 'GET', body = null) {
  const res = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'API error');
  return data;
}

function groupObjectives(objectives) {
  const byParent = new Map();
  objectives.forEach((obj) => {
    const parent = obj.parent_id ?? 'root';
    if (!byParent.has(parent)) byParent.set(parent, []);
    byParent.get(parent).push(obj);
  });

  const flattened = [];
  function walk(parent, level) {
    const children = byParent.get(parent) || [];
    children.sort((a, b) => a.position - b.position);
    children.forEach((child) => {
      flattened.push({ ...child, level });
      walk(child.id, level + 1);
    });
  }

  walk('root', 0);
  return flattened;
}

function cardsInCell(objectiveId, columnId) {
  return board.cards
    .filter((c) => c.objective_id === objectiveId && c.column_id === columnId)
    .sort((a, b) => a.position - b.position);
}

function renderHeader() {
  headerRow.innerHTML = '';
  const objectiveTh = document.createElement('th');
  objectiveTh.textContent = 'Objectius';
  objectiveTh.className = 'objective-col';
  headerRow.appendChild(objectiveTh);

  board.columns
    .sort((a, b) => a.position - b.position)
    .forEach((col) => {
      const th = document.createElement('th');
      th.textContent = col.title;
      headerRow.appendChild(th);
    });
}

function renderBoard() {
  renderHeader();
  boardBody.innerHTML = '';

  const objectives = groupObjectives(board.objectives);
  objectives.forEach((objective) => {
    const tr = document.createElement('tr');
    tr.dataset.objectiveId = objective.id;

    const titleTd = document.createElement('td');
    titleTd.className = 'objective-cell';
    titleTd.style.paddingLeft = `${12 + objective.level * 20}px`;

    const title = document.createElement('div');
    title.className = 'objective-title';
    title.textContent = objective.title;

    const controls = controlsTpl.content.firstElementChild.cloneNode(true);
    controls.addEventListener('click', (event) => onObjectiveAction(event, objective));

    titleTd.appendChild(title);
    titleTd.appendChild(controls);
    tr.appendChild(titleTd);

    board.columns
      .sort((a, b) => a.position - b.position)
      .forEach((column) => {
        const td = document.createElement('td');
        td.className = 'dropzone';
        td.dataset.objectiveId = objective.id;
        td.dataset.columnId = column.id;

        const addBtn = document.createElement('button');
        addBtn.className = 'add-card';
        addBtn.textContent = '+ Card';
        addBtn.addEventListener('click', () => addCard(objective.id, column.id));
        td.appendChild(addBtn);

        cardsInCell(objective.id, column.id).forEach((card) => td.appendChild(renderCard(card)));

        td.addEventListener('dragover', (e) => e.preventDefault());
        td.addEventListener('drop', (e) => onCardDrop(e, td));

        tr.appendChild(td);
      });

    boardBody.appendChild(tr);
  });
}

function renderCard(card) {
  const cardEl = document.createElement('article');
  cardEl.className = 'card';
  cardEl.draggable = true;
  cardEl.dataset.cardId = card.id;
  cardEl.dataset.objectiveId = card.objective_id;
  cardEl.dataset.columnId = card.column_id;

  const title = document.createElement('div');
  title.className = 'card-title';
  title.textContent = card.title;

  const delBtn = document.createElement('button');
  delBtn.className = 'delete-card';
  delBtn.textContent = '✕';
  delBtn.addEventListener('click', async () => {
    await api('card', 'DELETE', { id: card.id });
    await refresh();
  });

  cardEl.appendChild(title);
  cardEl.appendChild(delBtn);

  cardEl.addEventListener('dragstart', (e) => {
    e.dataTransfer.setData('application/json', JSON.stringify({ cardId: card.id }));
  });

  return cardEl;
}

async function onCardDrop(event, td) {
  event.preventDefault();
  const payload = JSON.parse(event.dataTransfer.getData('application/json') || '{}');
  if (!payload.cardId) return;

  const objectiveId = Number(td.dataset.objectiveId);
  const columnId = Number(td.dataset.columnId);
  const cardCount = td.querySelectorAll('.card').length;
  const newPosition = cardCount + 1;

  await api('card-move', 'PUT', {
    id: payload.cardId,
    objective_id: objectiveId,
    column_id: columnId,
    position: newPosition,
  });

  await refresh();
}

async function onObjectiveAction(event, objective) {
  const btn = event.target.closest('button');
  if (!btn) return;

  const action = btn.dataset.action;
  if (action === 'add-child') {
    const title = prompt('Títol del subobjectiu');
    if (!title) return;
    await api('objective', 'POST', { title, parent_id: objective.id });
  } else if (action === 'move-up') {
    await api('objective-move', 'PUT', { id: objective.id, direction: 'up' });
  } else if (action === 'move-down') {
    await api('objective-move', 'PUT', { id: objective.id, direction: 'down' });
  } else if (action === 'delete') {
    if (!confirm('Eliminar objectiu i subobjectius?')) return;
    await api('objective', 'DELETE', { id: objective.id });
  }

  await refresh();
}

async function addCard(objectiveId, columnId) {
  const title = prompt('Títol de la card');
  if (!title) return;
  await api('card', 'POST', { title, objective_id: objectiveId, column_id: columnId });
  await refresh();
}

addRootObjectiveBtn.addEventListener('click', async () => {
  const title = prompt('Títol del nou objectiu estratègic');
  if (!title) return;
  await api('objective', 'POST', { title, parent_id: null });
  await refresh();
});

async function refresh() {
  const data = await api('board', 'GET');
  board = data.data;
  renderBoard();
}

refresh().catch((err) => {
  console.error(err);
  alert(`Error carregant el panell: ${err.message}`);
});
