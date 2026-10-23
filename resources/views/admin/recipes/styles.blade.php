<style>
    .recipe-head,
    .recipe-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .recipe-head { margin-bottom: 26px; }
    .recipe-head p { margin: 0; }

    .recipe-button {
        display: inline-block;
        padding: 13px 20px;
        background: #153f32;
        color: white;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
    }

    .recipe-editor {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr);
        gap: 24px;
        align-items: start;
    }

    .recipe-stack {
        display: grid;
        gap: 24px;
        min-width: 0;
    }

    .recipe-card {
        background: white;
        border: 1px solid #dde7e1;
        border-radius: 16px;
        padding: 26px;
        min-width: 0;
    }

    .recipe-card h2 {
        margin: 0 0 12px;
        font-size: 20px;
    }

    .recipe-card p { margin-top: 0; }

    .recipe-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .recipe-editor select,
    .recipe-editor textarea,
    .recipe-filter select {
        width: 100%;
        padding: 13px;
        border: 1px solid #b9cbc0;
        border-radius: 8px;
        background: white;
        color: #17332c;
        font: inherit;
    }

    .recipe-editor textarea {
        resize: vertical;
        line-height: 1.6;
    }

    .recipe-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 125px auto;
        gap: 12px;
        align-items: end;
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid #e5ece7;
    }

    .recipe-row label {
        margin-top: 8px;
        font-size: 13px;
    }

    .recipe-remove {
        background: #fff0ee;
        color: #8b2920;
        padding: 13px;
    }

    .recipe-muted {
        color: #61756a;
        font-size: 13px;
        line-height: 1.6;
    }

    .recipe-table-wrap { overflow-x: auto; }

    .recipe-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .recipe-table th,
    .recipe-table td {
        padding: 15px 12px;
        border-bottom: 1px solid #e5ece7;
    }

    .recipe-table th {
        font-size: 12px;
        color: #61756a;
    }

    .recipe-table td { font-size: 14px; }

    .recipe-table td small {
        display: block;
        margin-top: 5px;
        color: #61756a;
    }

    .recipe-filter {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 220px auto auto;
        gap: 14px;
        align-items: end;
        margin-bottom: 24px;
    }

    .recipe-filter label { margin-top: 0; }

    .recipe-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 22px;
    }

    .recipe-save { margin-top: 24px; }

    @media (max-width: 1150px) {
        .recipe-editor { grid-template-columns: 1fr; }
    }

    @media (max-width: 650px) {
        .recipe-fields,
        .recipe-filter,
        .recipe-row { grid-template-columns: 1fr; }

        .recipe-card { padding: 20px; }
        .recipe-remove { justify-self: start; }
    }
</style>