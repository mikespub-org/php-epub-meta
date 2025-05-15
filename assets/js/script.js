// Main application code
const bookapi = {
    modal: null,
    
    resulttpl: `
        <div class="card result-card mb-3">
            <div class="row g-0">
                <div class="col-md-3">
                    <img src="" class="img-fluid rounded-start" alt="">
                </div>
                <div class="col-md-9">
                    <div class="card-body">
                        <div class="float-end">
                            <button class="btn btn-sm btn-primary btn-repl">Replace</button>
                            <button class="btn btn-sm btn-secondary btn-fill">Fill In</button>
                            <button class="btn btn-sm btn-info btn-cmp">Compare</button>
                        </div>
                        <h5 class="card-title title"></h5>
                        <p class="card-text authors"></p>
                        <p class="card-text description"></p>
                        <p class="card-text">
                            <small class="text-muted">
                                <span class="lang"></span>
                                <span class="publisher"></span>
                                <span class="subjects"></span>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    `,
    
    init() {
        this.modal = new bootstrap.Modal(document.getElementById('bookapi'));
        
        // Event listeners
        document.getElementById('lookup-data').addEventListener('click', () => this.open());
        document.querySelector('.btn-search').addEventListener('click', () => this.search());
        
        document.getElementById('bookapi-q').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.search();
            }
        });
        
        document.getElementById('bookapi-l').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.search();
            }
        });
    },
    
    open() {
        const query = document.querySelector('#bookpanel input[name=title]').value;
        document.getElementById('bookapi-q').value = query;
        const language = document.querySelector('#bookpanel input[name=language]').value;
        document.getElementById('bookapi-l').value = language;
        
        this.modal.show();
        this.search();
    },
    
    async search() {
        const output = document.getElementById('bookapi-out');
        output.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
        
        try {
            const params = new URLSearchParams({
                api: document.getElementById('bookapi-q').value,
                lang: document.getElementById('bookapi-l').value
            });
            const response = await fetch('?' + params.toString());
            
            const data = await response.json();
            this.searchdone(data);
        } catch (error) {
            output.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        }
    },
    
    searchdone(data) {
        const output = document.getElementById('bookapi-out');
        
        if (data.totalItems === 0) {
            if (data.error) {
                output.innerHTML = `<div class="alert alert-danger">Error: ${data.error}<br>Please try again later...</div>`;
                return;
            }
            output.innerHTML = '<div class="alert alert-warning">No results found.<br>Try adjusting the query and retry.</div>';
            return;
        }
        
        output.innerHTML = '';
        data.items.forEach(item => {
            const resultEl = document.createElement('div');
            resultEl.innerHTML = this.resulttpl;
            const result = resultEl.firstElementChild;
            
            if (item.volumeInfo.title) {
                result.querySelector('.title').textContent = item.volumeInfo.title;
            }
            if (item.volumeInfo.authors) {
                result.querySelector('.authors').textContent = item.volumeInfo.authors.join(', ');
            }
            if (item.volumeInfo.description) {
                result.querySelector('.description').textContent = item.volumeInfo.description;
            }
            if (item.volumeInfo.language) {
                result.querySelector('.lang').textContent = `[${item.volumeInfo.language}]`;
            }
            if (item.volumeInfo.publisher) {
                result.querySelector('.publisher').textContent = item.volumeInfo.publisher;
            }
            if (item.volumeInfo.categories) {
                result.querySelector('.subjects').textContent = item.volumeInfo.categories.join(', ');
            }
            if (item.volumeInfo.imageLinks?.thumbnail) {
                result.querySelector('img').src = item.volumeInfo.imageLinks.thumbnail;
            }
            
            result.querySelector('.btn-repl').addEventListener('click', () => this.replace(item.volumeInfo));
            result.querySelector('.btn-fill').addEventListener('click', () => this.fillin(item.volumeInfo));
            result.querySelector('.btn-cmp').addEventListener('click', () => this.compare(item.volumeInfo));
            
            output.appendChild(result);
        });
    },
    
    replace(item) {
        this.updateFields(item, false);
        this.modal.hide();
    },
    
    fillin(item) {
        this.updateFields(item, true);
        this.modal.hide();
    },
    
    compare(item) {
        document.querySelectorAll('.comparison').forEach(el => {
            const originalId = el.id.replace('2', '');
            const originalEl = document.getElementById(originalId);
            if (originalEl) {
                el.value = originalEl.value;
            }
        });
        
        this.updateFields(item, false, '2');
        document.getElementById('wrapper').classList.add('comparison-active');
        this.modal.hide();
    },
    
    updateFields(item, checkEmpty = false, suffix = '') {
        const setValue = (name, value) => {
            const el = document.querySelector(`#bookpanel [name="${name}${suffix}"]`);
            if (el && (!checkEmpty || !el.value)) {
                el.value = value;
                if (name === 'description') {
                    if (quillEditor) {
                        quillEditor.root.innerHTML = value;
                    }
                }
            }
        };
        
        if (item.title) setValue('title', item.title);
        if (item.description) setValue('description', item.description);
        if (item.language) setValue('language', item.language);
        if (item.publisher) setValue('publisher', item.publisher);
        if (item.categories) setValue('subjects', item.categories.join(', '));
        if (item.imageLinks?.thumbnail) {
            setValue('coverurl', item.imageLinks.thumbnail);
            document.getElementById('cover').src = item.imageLinks.thumbnail;
            document.getElementById('cover').classList.remove('noimg');
        }
    }
};

// Author management
const author = {
    init() {
        document.getElementById('add-author').addEventListener('click', () => this.add());
        
        // Add remove buttons to existing author rows
        document.querySelectorAll('.author-row').forEach(row => {
            this.addRemoveButton(row);
        });
    },
    
    createAuthorRow(index) {
        const row = document.createElement('div');
        row.className = 'author-row mb-2';
        row.innerHTML = `
            <div class="row g-2">
                <div class="col">
                    <input type="text" class="form-control" name="authorname[${index}]" placeholder="Author Name">
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="authoras[${index}]" placeholder="Sort As">
                </div>
            </div>
        `;
        this.addRemoveButton(row);
        return row;
    },
    
    addRemoveButton(row) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-link text-danger remove-author';
        btn.innerHTML = '&times;';
        btn.addEventListener('click', () => row.remove());
        row.appendChild(btn);
    },
    
    add() {
        const container = document.getElementById('authors');
        const index = container.querySelectorAll('.author-row').length;
        container.appendChild(this.createAuthorRow(index));
    }
};

// Rich text editor initialization
let quillEditor = null;

function initializeEditor() {
    quillEditor = new Quill('#description', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'header': [1, 2, 3, 4, 5, false] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link'],
                ['clean']
            ]
        },
        formats: [
            'bold', 'italic', 'underline', 'strike',
            'header', 'list', 'align', 'link'
        ]
    });
    // Keep hidden input in sync with editor content
    quillEditor.on('text-change', function() {
        document.querySelector('#description-input').value = quillEditor.root.innerHTML;
    });
    // Set initial value to hidden input
    document.querySelector('#description-input').value = quillEditor.root.innerHTML;
}

// Initialize everything when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    bookapi.init();
    author.init();
    initializeEditor();

    // Scroll to currently selected book
    //const current = document.querySelector('#booklist .active');
    //if (current) {
    //    current.scrollIntoView({ behavior: 'smooth' });
    //}
});
