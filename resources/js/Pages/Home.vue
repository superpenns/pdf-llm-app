<script setup>
import {useForm} from "@inertiajs/vue3";
import {ref, defineProps } from "vue";

const form = useForm({
    file:null
})

const processing = ref(false);
const oversizedFile = ref(false);
const fileInput = ref(null);

const props = defineProps({
    pdfResults: Array,
});

const submitForm = () => {
    processing.value = true;

    form.post('/', {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            resetFileInput();
            processing.value = false;
        },
        onError: (errors) => {
            console.error("Validation Errors:", errors);
            form.reset();
            resetFileInput();
            processing.value = false;
        }
    });
};

const fileSelected = (e) => {
    form.clearErrors('file');
    oversizedFile.value = e.target.files[0].size > 10240000;
    form.file = e.target.files[0];
};

const resetFileInput = () => {
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

</script>

<template>
    <div class="flex items-center justify-center h-screen w-full bg-gray-100">
        
        <div class="flex flex-col h-screen bg-gray-100">

            <div class="bg-white max-w-md m-3 p-6 rounded-lg shadow-lg">
                <h1 class="text-2xl font-bold mb-4">PDF -> Zusammenfassung</h1>
                <h3 class="text-l mb-4">Laden Sie Ihr PDF hoch, und Sie erhalten eine prägnante, hochwertige Zusammenfassung von einem Large Language Model.</h3>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div v-if="form.errors.file" class="text-red-500 text-sm">
                        {{ form.errors.file }}
                    </div>
                    <div :class="{'!text-red-500' : oversizedFile}">
                        {{ oversizedFile ? "Die ausgewählte Datei ist größer als 10 MB" : "Datei (maximale Größe 10MB)" }}
                    </div>
                    <input
                        ref="fileInput"
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
                            Hochladen
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white flex-1 overflow-y-auto max-w-md m-3 p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-bold mb-4">Zusammenfassungen</h2>
                <div v-if="processing" class="text-center">
                    <span class="animate-spin inline-block w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full"></span>
                    <p class="text-gray-500 mt-2">In Bearbeitung ...</p>
                </div>

                <div v-if="props.pdfResults.length === 0" class="text-gray-500">Noch keine Ergebnisse</div>
                <ul v-else>
                    <li v-for="(result, index) in props.pdfResults" :key="index" class="mb-4">
                        <h3 class="font-semibold">Dateiname: {{ result.name }}</h3>
                        <p class="text-sm bg-gray-100 p-2 rounded">{{ result.content }}</p>
                    </li>
                </ul>
            </div>
        </div>
        
    </div>
</template>