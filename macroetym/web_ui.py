import streamlit as st
from macroetym.main import Text
import os
import pandas as pd
import plotly.express as px
import spacy
from spacy import displacy

def run():
    st.title("Macro-Etymological Analyzer")

    # Text selection
    st.subheader("Text Selection")
    preloaded_texts_dir = "texts/"
    try:
        preloaded_files = [f for f in os.listdir(preloaded_texts_dir) if os.path.isfile(os.path.join(preloaded_texts_dir, f))]
    except FileNotFoundError:
        preloaded_files = []

    text_selection = st.radio("Choose a text to analyze:", ["Upload a file"] + preloaded_files)

    uploaded_file = None
    if text_selection == "Upload a file":
        uploaded_file = st.file_uploader("Drag and drop a file or click to upload", type=['txt'])
    
    text_to_analyze = ""
    if uploaded_file is not None:
        text_to_analyze = uploaded_file.read().decode('utf-8')
    elif text_selection != "Upload a file":
        with open(os.path.join(preloaded_texts_dir, text_selection), 'r', encoding='utf-8') as f:
            text_to_analyze = f.read()

    if text_to_analyze:
        st.header("Analysis Results")
        
        # Perform analysis
        with st.spinner("Analyzing text..."):
            try:
                text_obj = Text(text_to_analyze)
                family_stats = text_obj.familyStats()

                # Display pie chart
                st.subheader("Language Family Distribution")
                if family_stats:
                    family_series = pd.Series(family_stats)
                    fig = px.pie(family_series, values=family_series.values, names=family_series.index)
                    st.plotly_chart(fig)
                else:
                    st.write("Could not generate statistics for this text.")

                # Display annotated text
                st.subheader("Annotated Text")
                
                ents = []
                for token in text_obj.doc:
                    if token._.parent_languages and token._.parent_languages.langs:
                        lang_info = " < ".join(token._.parent_languages.langs)
                        ents.append({
                            "start": token.idx,
                            "end": token.idx + len(token.text),
                            "label": lang_info
                        })
                
                displacy_input = [{"text": text_obj.doc.text, "ents": ents}]
                html = displacy.render(displacy_input, style="ent", manual=True, jupyter=False)
                st.write(html, unsafe_allow_html=True)
                    
            except Exception as e:
                st.error(f"An error occurred during analysis: {e}")

if __name__ == "__main__":
    run()

