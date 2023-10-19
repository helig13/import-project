import $ from 'jquery';

window.$ = $;
import './bootstrap';

let accumulatedTotalRows = 0;
let accumulatedSkippedRows = 0;
let accumulatedDuplicates = 0;
window.Echo.channel('import-progress')
    .listen('ImportProgressUpdated', (e) => {
        accumulatedTotalRows += e.totalFileRows;
        accumulatedSkippedRows += e.skippedRowsCumulative;
        accumulatedDuplicates += e.duplicatesCumulative;
        let progress = (accumulatedTotalRows - accumulatedSkippedRows - accumulatedDuplicates) / accumulatedTotalRows * 100;
        document.getElementById("progressBar").style.width = progress + "%";
        document.getElementById("totalFileRows").innerText = accumulatedTotalRows;
        document.getElementById("skippedCount").innerText = accumulatedSkippedRows;
        document.getElementById("duplicateCount").innerText = accumulatedDuplicates;
        document.getElementById("processingTime").innerText = e.processingTime;
    });




