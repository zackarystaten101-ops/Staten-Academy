import React, { useState, useEffect } from 'react';

interface VocabularyWord {
  id: number;
  word: string;
  definition: string;
  example_sentence: string;
  category: string;
  audio_url: string | null;
}

interface VocabularyPanelProps {
  teacherId: string;
  sessionId: string;
  onClose: () => void;
  onAddToBoard?: (word: string, definition: string) => void;
}

const VocabularyPanel: React.FC<VocabularyPanelProps> = ({ teacherId, sessionId, onClose, onAddToBoard }) => {
  const [words, setWords] = useState<VocabularyWord[]>([]);
  const [loading, setLoading] = useState(true);
  const [showAddForm, setShowAddForm] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [categories, setCategories] = useState<string[]>([]);

  const [newWord, setNewWord] = useState({
    word: '',
    definition: '',
    example_sentence: '',
    category: 'general'
  });

  useEffect(() => {
    loadVocabulary();
  }, [teacherId]);

  const loadVocabulary = async () => {
    try {
      const response = await fetch(`/api/vocabulary.php?teacherId=${teacherId}`);
      if (response.ok) {
        const data = await response.json();
        setWords(data.words || []);
        
        // Extract unique categories
        const uniqueCategories = Array.from(new Set(data.words?.map((w: VocabularyWord) => w.category) || []));
        setCategories(['all', ...uniqueCategories]);
      }
    } catch (error) {
      console.error('Error loading vocabulary:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleAddWord = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      const response = await fetch('/api/vocabulary.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          teacher_id: teacherId,
          ...newWord
        })
      });

      if (response.ok) {
        const data = await response.json();
        setWords(prev => [...prev, data.word]);
        setNewWord({
          word: '',
          definition: '',
          example_sentence: '',
          category: 'general'
        });
        setShowAddForm(false);
      }
    } catch (error) {
      console.error('Error adding word:', error);
      alert('Error adding word');
    }
  };

  const handleSendToBoard = (word: string, definition: string) => {
    onAddToBoard?.(word, definition);
  };

  const handleExport = async (format: 'csv' | 'pdf') => {
    try {
      const response = await fetch(`/api/vocabulary.php?action=export&format=${format}&teacherId=${teacherId}`);
      if (response.ok) {
        if (format === 'csv') {
          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `vocabulary-${Date.now()}.csv`;
          a.click();
        } else {
          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);
          window.open(url);
        }
      }
    } catch (error) {
      console.error('Error exporting vocabulary:', error);
      alert('Error exporting vocabulary');
    }
  };

  const handleImport = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('teacher_id', teacherId);

    try {
      const response = await fetch('/api/vocabulary.php?action=import', {
        method: 'POST',
        body: formData
      });

      if (response.ok) {
        loadVocabulary();
        alert('Vocabulary imported successfully');
      }
    } catch (error) {
      console.error('Error importing vocabulary:', error);
      alert('Error importing vocabulary');
    }
  };

  const filteredWords = words.filter(word => {
    const matchesSearch = word.word.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         word.definition.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = selectedCategory === 'all' || word.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  return (
    <div className="vocabulary-panel">
      <div className="vocabulary-panel-header">
        <h3 className="vocabulary-panel-title">Vocabulary</h3>
        <button className="vocabulary-panel-close" onClick={onClose}>
          <i className="fas fa-times"></i>
        </button>
      </div>

      <div className="vocabulary-panel-content">
        {/* Search and Filter */}
        <div style={{ marginBottom: '15px' }}>
          <input
            type="text"
            placeholder="Search words..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            style={{
              width: '100%',
              padding: '8px',
              border: '1px solid #dee2e6',
              borderRadius: '8px',
              marginBottom: '10px'
            }}
          />
          <select
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
            style={{
              width: '100%',
              padding: '8px',
              border: '1px solid #dee2e6',
              borderRadius: '8px'
            }}
          >
            {categories.map(cat => (
              <option key={cat} value={cat}>
                {cat === 'all' ? 'All Categories' : cat}
              </option>
            ))}
          </select>
        </div>

        {/* Add Word Button */}
        <button
          className="vocab-btn vocab-btn-primary"
          onClick={() => setShowAddForm(!showAddForm)}
          style={{ width: '100%', marginBottom: '15px' }}
        >
          <i className="fas fa-plus"></i> Add New Word
        </button>

        {/* Add Word Form */}
        {showAddForm && (
          <div className="add-word-form">
            <form onSubmit={handleAddWord}>
              <div className="form-group">
                <label>Word</label>
                <input
                  type="text"
                  value={newWord.word}
                  onChange={(e) => setNewWord({ ...newWord, word: e.target.value })}
                  required
                />
              </div>
              <div className="form-group">
                <label>Definition</label>
                <textarea
                  value={newWord.definition}
                  onChange={(e) => setNewWord({ ...newWord, definition: e.target.value })}
                  required
                  rows={3}
                />
              </div>
              <div className="form-group">
                <label>Example Sentence</label>
                <textarea
                  value={newWord.example_sentence}
                  onChange={(e) => setNewWord({ ...newWord, example_sentence: e.target.value })}
                  rows={2}
                />
              </div>
              <div className="form-group">
                <label>Category</label>
                <input
                  type="text"
                  value={newWord.category}
                  onChange={(e) => setNewWord({ ...newWord, category: e.target.value })}
                  placeholder="e.g., verbs, nouns, adjectives"
                />
              </div>
              <div style={{ display: 'flex', gap: '10px' }}>
                <button type="submit" className="vocab-btn vocab-btn-primary" style={{ flex: 1 }}>
                  Add Word
                </button>
                <button
                  type="button"
                  className="vocab-btn vocab-btn-secondary"
                  onClick={() => setShowAddForm(false)}
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Export/Import */}
        <div style={{ marginBottom: '15px', display: 'flex', gap: '5px' }}>
          <button
            className="vocab-btn vocab-btn-secondary"
            onClick={() => handleExport('csv')}
            style={{ flex: 1, fontSize: '0.8rem' }}
          >
            <i className="fas fa-download"></i> CSV
          </button>
          <button
            className="vocab-btn vocab-btn-secondary"
            onClick={() => handleExport('pdf')}
            style={{ flex: 1, fontSize: '0.8rem' }}
          >
            <i className="fas fa-file-pdf"></i> PDF
          </button>
          <label className="vocab-btn vocab-btn-secondary" style={{ flex: 1, fontSize: '0.8rem', cursor: 'pointer', textAlign: 'center' }}>
            <i className="fas fa-upload"></i> Import
            <input
              type="file"
              accept=".csv"
              onChange={handleImport}
              style={{ display: 'none' }}
            />
          </label>
        </div>

        {/* Word List */}
        {loading ? (
          <div style={{ textAlign: 'center', padding: '20px' }}>
            <i className="fas fa-spinner fa-spin"></i> Loading...
          </div>
        ) : filteredWords.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '20px', color: '#6c757d' }}>
            No vocabulary words found
          </div>
        ) : (
          filteredWords.map(word => (
            <div key={word.id} className="vocabulary-word-item">
              <div className="vocabulary-word-header">
                <div className="vocabulary-word">{word.word}</div>
                {word.audio_url && (
                  <button
                    className="vocab-btn vocab-btn-secondary"
                    onClick={() => {
                      const audio = new Audio(word.audio_url!);
                      audio.play();
                    }}
                    style={{ padding: '4px 8px', fontSize: '0.8rem' }}
                  >
                    <i className="fas fa-volume-up"></i>
                  </button>
                )}
              </div>
              <div className="vocabulary-word-definition">{word.definition}</div>
              {word.example_sentence && (
                <div className="vocabulary-word-example">"{word.example_sentence}"</div>
              )}
              <div className="vocabulary-word-actions">
                <button
                  className="vocab-btn vocab-btn-primary"
                  onClick={() => handleSendToBoard(word.word, word.definition)}
                >
                  <i className="fas fa-paper-plane"></i> Send to Board
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default VocabularyPanel;

