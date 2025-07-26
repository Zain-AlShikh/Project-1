# recommendation_api.py

from flask import Flask, request, jsonify
import mysql.connector
import os, json
import pandas as pd
from dotenv import load_dotenv
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

load_dotenv()

app = Flask(__name__)

def connect_db():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        user=os.getenv("DB_USERNAME", os.getenv("DB_USER")),
        password=os.getenv("DB_PASSWORD", os.getenv("DB_PASS")),
        database=os.getenv("DB_DATABASE")
    )

def get_books_df():
    """
    يجلب id, title, description, subject, author_name, category_name من جدول الكتب.
    subject مفترض مخزن JSON array كنص.
    """
    conn = connect_db()
    query = """
        SELECT
            b.id,
            COALESCE(b.title, '') AS title,
            COALESCE(b.description, '') AS description,
            COALESCE(b.subject, '[]') AS subject_json,
            COALESCE(a.name, '') AS author_name,
            COALESCE(c.name, '') AS category_name
        FROM books b
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN categories c ON b.category_id = c.id
    """
    df = pd.read_sql(query, conn)
    conn.close()

   
    def subjects_to_text(x):
        try:
            arr = json.loads(x) if isinstance(x, str) else []
            # arr يكون قائمة من نصوص
            return ' '.join([str(s) for s in arr]) if arr else ''
        except:
            return ''
    df['subject_text'] = df['subject_json'].apply(subjects_to_text)
    # أنشئ عمود نصي موحّد
    df['text'] = (
        df['title'].fillna('') + ' '
        + df['description'].fillna('') + ' '
        + df['subject_text'].fillna('') + ' '
        + df['author_name'].fillna('') + ' '
        + df['category_name'].fillna('')
    )
    return df[['id', 'text']]

def get_book_by_id(book_id):
    """
    يجلب حقل title, description, subject, author_name, category_name لكتاب واحد.
    """
    conn = connect_db()
    cursor = conn.cursor(dictionary=True)
    query = """
        SELECT
            b.id,
            COALESCE(b.title, '') AS title,
            COALESCE(b.description, '') AS description,
            COALESCE(b.subject, '[]') AS subject_json,
            COALESCE(a.name, '') AS author_name,
            COALESCE(c.name, '') AS category_name
        FROM books b
        LEFT JOIN authors a ON b.author_id = a.id
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.id = %s
    """
    cursor.execute(query, (book_id,))
    row = cursor.fetchone()
    cursor.close()
    conn.close()
    if not row:
        return None
    # فك subject
    try:
        arr = json.loads(row['subject_json']) if row['subject_json'] else []
        subject_text = ' '.join([str(s) for s in arr]) if arr else ''
    except:
        subject_text = ''
    # أنشئ نص موحّد
    text = (
        row['title'] + ' ' +
        row['description'] + ' ' +
        subject_text + ' ' +
        row['author_name'] + ' ' +
        row['category_name']
    )
    return {
        "id": row['id'],
        "text": text
    }

def get_user_preference_book_ids(user_id):
    """
    يجلب book_id من جدول book_user_ratings و book_user_favorites لـ user_id.
    """
    conn = connect_db()
    cursor = conn.cursor()
    rated = []
    fav = []
    # تقييمات
    cursor.execute("SELECT book_id FROM book_user_ratings WHERE user_id = %s", (user_id,))
    for r in cursor.fetchall():
        try:
            rated.append(int(r[0]))
        except:
            pass
    # مفضلات
    cursor.execute("SELECT book_id FROM book_user_favorites WHERE user_id = %s", (user_id,))
    for r in cursor.fetchall():
        try:
            fav.append(int(r[0]))
        except:
            pass
    cursor.close()
    conn.close()
    return list(set(rated + fav))

# ===========================================================
# 1. API لاقتراح كتب مشابهة لكتاب معين بواسطة id فقط
# ===========================================================
@app.route('/similar-books', methods=['POST'])
def similar_books():
    data = request.get_json()
    if not data or 'id' not in data:
        return jsonify({'error': 'Book id is required'}), 400
    try:
        book_id = int(data['id'])
    except:
        return jsonify({'error': 'Book id must be integer'}), 400

    target = get_book_by_id(book_id)
    if not target:
        return jsonify({'error': 'Book not found'}), 404

    books_df = get_books_df()
    # استبعاد الكتاب نفسه
    # ولكن نحتاج index: pandas index where id == book_id
    # لننشئ مصفوفة نصوص corpus: جميع الكتب ثم الهدف في النهاية
    texts = books_df['text'].tolist()
    ids = books_df['id'].tolist()
    # العثور على موقع الكتاب المستهدف في القائمة
    try:
        idx = ids.index(book_id)
    except ValueError:
        idx = None

    # نبني corpus: جميع الكتب ثم target
    corpus = texts + [target['text']]
    # TF-IDF
    vectorizer = TfidfVectorizer(stop_words='english')
    tfidf_matrix = vectorizer.fit_transform(corpus)
    # تشابه الهدف مع جميع الكتب
    similarities = cosine_similarity(tfidf_matrix[-1], tfidf_matrix[:-1]).flatten()
    # نرتب ونجلب أعلى 5 مشابهات
    # إن idx ليس None، نريد استبعاد index idx من النتائج.
    # لنضع تشابه idx = -1 ليُستبعد:
    if idx is not None and 0 <= idx < len(similarities):
        similarities[idx] = -1.0
    # نختار أعلى القيم
    top_n = 5
    # إذا عدد الكتب أقل من top_n، نجلب كل النتائج المتاحة ما عدا نفسه
    # argsort ثم tail
    sorted_idx = similarities.argsort()[::-1]
    result_ids = []
    for i in sorted_idx:
        if similarities[i] <= 0:
            break
        result_ids.append(ids[i])
        if len(result_ids) >= top_n:
            break

    return jsonify({'book_ids': result_ids})

# ===========================================================
# 2. API لاقتراح كتب بناءً على تفضيلات المستخدم (user_id)
# ===========================================================
@app.route('/recommend-from-user', methods=['POST'])
def recommend_from_user():
    data = request.get_json()
    if not data or 'user_id' not in data:
        return jsonify({'error': 'user_id is required'}), 400
    try:
        user_id = int(data['user_id'])
    except:
        return jsonify({'error': 'user_id must be integer'}), 400

    pref_ids = get_user_preference_book_ids(user_id)
    if not pref_ids:
        return jsonify({'book_ids': []})

    books_df = get_books_df()
    ids = books_df['id'].tolist()
    texts = books_df['text'].tolist()

    # نص الهدف: جمع نصوص الكتب المفضّلة أو المقَيّمة
    target_texts = []
    id_to_text = dict(zip(ids, texts))
    for bid in pref_ids:
        if bid in id_to_text:
            target_texts.append(id_to_text[bid])
    if not target_texts:
        return jsonify({'book_ids': []})
    combined_target = ' '.join(target_texts)

    # corpus: جميع الكتب ثم combined_target
    corpus = texts + [combined_target]
    vectorizer = TfidfVectorizer(stop_words='english')
    tfidf_matrix = vectorizer.fit_transform(corpus)
    similarities = cosine_similarity(tfidf_matrix[-1], tfidf_matrix[:-1]).flatten()

    # استبعاد كتب المستخدم نفسه
    for bid in pref_ids:
        if bid in ids:
            idx = ids.index(bid)
            similarities[idx] = -1.0

    # جلب أعلى 7 توصيات
    sorted_idx = similarities.argsort()[::-1]
    result_ids = []
    top_n = 7
    for i in sorted_idx:
        if similarities[i] <= 0:
            break
        result_ids.append(ids[i])
        if len(result_ids) >= top_n:
            break

    return jsonify({'book_ids': result_ids})

if __name__ == '__main__':
    # عند التشغيل محلياً
    app.run(debug=True, port=5001)
