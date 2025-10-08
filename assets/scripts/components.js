import { appState } from './state';

// Function to render the package table
export function renderPackageTable() {
  const tableBody = document.querySelector('#package-table tbody');

  // Clear the table body
  tableBody.innerHTML = '';

  // Check for global error state
  const { error, packages } = appState.get();
  if (error) {
    const errorRow = document.createElement('tr');
    const errorCell = document.createElement('td');
    errorCell.colSpan = 5; // Adjust based on the number of columns

    // Create error message
    const errorMessage = document.createElement('span');
    errorMessage.textContent = `Error: ${error}`;
    errorMessage.style.color = 'red';

    // Create retry button
    const retryButton = document.createElement('button');
    retryButton.textContent = 'Retry';
    retryButton.style.marginLeft = '10px';
    retryButton.addEventListener('click', () => {
      appState.setKey('error', null); // Clear the error state
      actions['sync-packages'](); // Retry the sync action
    });

    // Append error message and retry button to the cell
    errorCell.appendChild(errorMessage);
    errorCell.appendChild(retryButton);
    errorRow.appendChild(errorCell);
    tableBody.appendChild(errorRow);
    return;
  }

  // Render packages if no error
  if (packages.length === 0) {
    const emptyRow = document.createElement('tr');
    const emptyCell = document.createElement('td');
    emptyCell.colSpan = 5; // Adjust based on the number of columns
    emptyCell.textContent = 'No repositories found.';
    emptyRow.appendChild(emptyCell);
    tableBody.appendChild(emptyRow);
    return;
  }

  packages.forEach((pkg) => {
    const row = document.createElement('tr');
    // Populate row with package data
    const nameCell = document.createElement('td');
    nameCell.textContent = pkg.name;
    row.appendChild(nameCell);
    // Add other cells as needed
    tableBody.appendChild(row);
  });
}