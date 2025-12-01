/**
 * Lesson Editor Component
 *
 * Modal for editing lesson details.
 */

import React, { useState, useEffect } from 'react';

const LESSON_TYPES = [
    { value: 'video', label: 'Video', icon: 'dashicons-video-alt3' },
    { value: 'text', label: 'Text/Article', icon: 'dashicons-media-document' },
    { value: 'quiz', label: 'Quiz', icon: 'dashicons-forms' },
    { value: 'assignment', label: 'Assignment', icon: 'dashicons-portfolio' },
    { value: 'download', label: 'Download', icon: 'dashicons-download' },
];

const LessonEditor = ({ lesson, onSave, onClose, restUrl, nonce }) => {
    const [formData, setFormData] = useState({
        title: lesson.title || '',
        type: lesson.type || 'video',
        description: lesson.description || '',
        duration: lesson.duration || 0,
        videoUrl: lesson.videoUrl || '',
        videoProvider: lesson.videoProvider || 'youtube',
        content: lesson.content || '',
        isFree: lesson.isFree || false,
        isPreview: lesson.isPreview || false,
        completionType: lesson.completionType || 'video',
        downloadFiles: lesson.downloadFiles || [],
        quizId: lesson.quizId || '',
    });

    const [quizzes, setQuizzes] = useState([]);
    const [loadingQuizzes, setLoadingQuizzes] = useState(false);

    useEffect(() => {
        if (formData.type === 'quiz') {
            loadQuizzes();
        }
    }, [formData.type]);

    const loadQuizzes = async () => {
        setLoadingQuizzes(true);
        try {
            const response = await fetch(`${restUrl}quizzes`, {
                headers: { 'X-WP-Nonce': nonce },
            });
            const data = await response.json();
            setQuizzes(data);
        } catch (error) {
            console.error('Failed to load quizzes:', error);
        } finally {
            setLoadingQuizzes(false);
        }
    };

    const handleChange = (field, value) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        onSave(formData);
    };

    return (
        <div className="sfls-modal-overlay" onClick={onClose}>
            <div className="sfls-modal" onClick={(e) => e.stopPropagation()}>
                <div className="sfls-modal-header">
                    <h3>{lesson.isNew ? 'Add New Lesson' : 'Edit Lesson'}</h3>
                    <button type="button" className="sfls-modal-close" onClick={onClose}>
                        <span className="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="sfls-modal-body">
                        <div className="sfls-form-row">
                            <label htmlFor="lesson-title">Lesson Title</label>
                            <input
                                type="text"
                                id="lesson-title"
                                value={formData.title}
                                onChange={(e) => handleChange('title', e.target.value)}
                                required
                                className="sfls-input"
                            />
                        </div>

                        <div className="sfls-form-row">
                            <label>Lesson Type</label>
                            <div className="sfls-type-selector">
                                {LESSON_TYPES.map((type) => (
                                    <button
                                        key={type.value}
                                        type="button"
                                        className={`sfls-type-btn ${formData.type === type.value ? 'active' : ''}`}
                                        onClick={() => handleChange('type', type.value)}
                                    >
                                        <span className={`dashicons ${type.icon}`}></span>
                                        {type.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {formData.type === 'video' && (
                            <>
                                <div className="sfls-form-row">
                                    <label>Video Provider</label>
                                    <select
                                        value={formData.videoProvider}
                                        onChange={(e) => handleChange('videoProvider', e.target.value)}
                                        className="sfls-select"
                                    >
                                        <option value="youtube">YouTube</option>
                                        <option value="vimeo">Vimeo</option>
                                        <option value="wistia">Wistia</option>
                                        <option value="bunny">Bunny Stream</option>
                                        <option value="self">Self-hosted</option>
                                    </select>
                                </div>

                                <div className="sfls-form-row">
                                    <label htmlFor="video-url">Video URL</label>
                                    <input
                                        type="url"
                                        id="video-url"
                                        value={formData.videoUrl}
                                        onChange={(e) => handleChange('videoUrl', e.target.value)}
                                        placeholder="https://..."
                                        className="sfls-input"
                                    />
                                </div>

                                <div className="sfls-form-row">
                                    <label htmlFor="duration">Duration (minutes)</label>
                                    <input
                                        type="number"
                                        id="duration"
                                        value={formData.duration}
                                        onChange={(e) => handleChange('duration', parseInt(e.target.value) || 0)}
                                        min="0"
                                        className="sfls-input sfls-input-small"
                                    />
                                </div>

                                <div className="sfls-form-row">
                                    <label>Completion Requirement</label>
                                    <select
                                        value={formData.completionType}
                                        onChange={(e) => handleChange('completionType', e.target.value)}
                                        className="sfls-select"
                                    >
                                        <option value="video">Watch Video (with tracking)</option>
                                        <option value="manual">Mark Complete Manually</option>
                                        <option value="auto">Auto-complete on View</option>
                                    </select>
                                </div>
                            </>
                        )}

                        {formData.type === 'text' && (
                            <div className="sfls-form-row">
                                <label htmlFor="content">Content</label>
                                <textarea
                                    id="content"
                                    value={formData.content}
                                    onChange={(e) => handleChange('content', e.target.value)}
                                    rows="10"
                                    className="sfls-textarea"
                                    placeholder="Enter lesson content..."
                                ></textarea>
                            </div>
                        )}

                        {formData.type === 'quiz' && (
                            <div className="sfls-form-row">
                                <label>Select Quiz</label>
                                {loadingQuizzes ? (
                                    <p>Loading quizzes...</p>
                                ) : (
                                    <select
                                        value={formData.quizId}
                                        onChange={(e) => handleChange('quizId', e.target.value)}
                                        className="sfls-select"
                                    >
                                        <option value="">-- Select Quiz --</option>
                                        {quizzes.map((quiz) => (
                                            <option key={quiz.id} value={quiz.id}>{quiz.title}</option>
                                        ))}
                                    </select>
                                )}
                            </div>
                        )}

                        <div className="sfls-form-row">
                            <label htmlFor="description">Description (optional)</label>
                            <textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) => handleChange('description', e.target.value)}
                                rows="3"
                                className="sfls-textarea"
                            ></textarea>
                        </div>

                        <div className="sfls-form-row sfls-checkboxes">
                            <label className="sfls-checkbox">
                                <input
                                    type="checkbox"
                                    checked={formData.isFree}
                                    onChange={(e) => handleChange('isFree', e.target.checked)}
                                />
                                <span>Free Lesson</span>
                            </label>
                            <label className="sfls-checkbox">
                                <input
                                    type="checkbox"
                                    checked={formData.isPreview}
                                    onChange={(e) => handleChange('isPreview', e.target.checked)}
                                />
                                <span>Available for Preview</span>
                            </label>
                        </div>
                    </div>

                    <div className="sfls-modal-footer">
                        <button type="button" className="button" onClick={onClose}>
                            Cancel
                        </button>
                        <button type="submit" className="button button-primary">
                            {lesson.isNew ? 'Add Lesson' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default LessonEditor;
