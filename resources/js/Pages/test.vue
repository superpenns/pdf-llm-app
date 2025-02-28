<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { defineProps } from "vue";

const props = defineProps({
    pdfResults: Array, // Get results from Laravel
});

const form = useForm({
    file: null
});

const submitForm = () => {
    form.post(route('pdf.upload'), {
        forceFormData: true,
        onSuccess: () => {
            alert("File uploaded successfully!");
            form.reset();
        },
        onError: (errors) => {
            console.error("Validation Errors:", errors);
        }
    });
};

const oversizedFile = ref(false);
const fileSelected = (e) => {
    oversizedFile.value = e.target.files[0].size > 10240000;
    form.file = e.target.files[0];
};
</script>

<template>
    <div class="flex items-center justify-center h-screen w-full bg-gray-100">
        <div class="bg-white mx-auto p-8 rounded-lg shadow-lg">
            <h1 class="text-2xl font-bold mb-4">PDF -> Zusammenfassung</h1>
            <h3 class="text-l mb-4">Laden Sie Ihr PDF hoch, und Sie erhalten eine prägnante, hochwertige Zusammenfassung von Large Language Model.</h3>
            <form @submit.prevent="submitForm" class="space-y-4">
                <div v-if="form.errors.file" class="text-red-500 text-sm">
                    {{ form.errors.file }}
                </div>
                <div :class="{'!text-red-500' : oversizedFile}">
                    {{ oversizedFile ? "Die ausgewählte Datei ist größer als 10 MB" : "Datei (maximale Größe 10MB)" }}
                </div>
                <input
                    @input="fileSelected"
                    type="file"
                    id="pdf"
                    class="rounded border p-2"
                    :class="{'!border-red-500' : oversizedFile}"
                />
                <div>
                    <button
                        type="submit"
                        class="bg-blue-500 text-white px-4 py-2 rounded disabled:bg-gray-400 disabled:cursor-not-allowed"
                        :disabled="oversizedFile || !form.file"
                    >
                        Upload
                    </button>
                </div>
            </form>
        </div>
        <div class="bg-white mx-auto p-8 rounded-lg shadow-lg w-full">
            <h2 class="text-xl font-bold mb-4">Ergebnisse der PDF-Verarbeitung</h2>
            <div v-if="props.pdfResults.length === 0" class="text-gray-500">Noch keine Ergebnisse</div>
            <ul v-else>
                <li v-for="(result, index) in props.pdfResults" :key="index" class="mb-4">
                    <h3 class="font-semibold">{{ result.name }}</h3>
                    <p class="text-sm bg-gray-100 p-2 rounded">{{ result.content }}</p>
                </li>
            </ul>
        </div>
    </div>
</template>